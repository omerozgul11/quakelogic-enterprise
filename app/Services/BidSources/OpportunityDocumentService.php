<?php

namespace App\Services\BidSources;

use App\Models\Opportunity;
use App\Services\BidSources\SamGov\SamGovConnector;
use App\Services\BidSources\SamGov\SamLinkResolver;
use App\Services\BidSources\SamGov\SamThrottledException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Surfaces the solicitation documents an opportunity carries from its source
 * feed (SAM.gov). SAM's Opportunities API v2 exposes downloadable files under
 * "resourceLinks" in the raw record; we never store the files themselves, so
 * previews/downloads are proxied on demand (with the API key kept server-side).
 *
 * Not every opportunity arrives with those links: SAM full-text imports store
 * the UI-search record (no resourceLinks) and BidPrime leads carry only email
 * metadata. ensure() back-fills them — whenever a SAM.gov notice id can be
 * resolved for the record, the official SAM record is fetched and its
 * resourceLinks merged into raw_source_data so the documents become available.
 */
class OpportunityDocumentService
{
    public function __construct(
        private readonly SamGovConnector $sam,
        private readonly SamLinkResolver $samLinks,
    ) {}

    /**
     * The downloadable documents already on an opportunity's source record.
     *
     * @return array<int,array{index:int,name:string,url:string}>
     */
    public function list(Opportunity $opportunity): array
    {
        $urls = $this->rawLinks($opportunity);

        return array_map(
            fn (string $url, int $i) => ['index' => $i, 'name' => $this->nameFor($url, $i), 'url' => $url],
            $urls,
            array_keys($urls),
        );
    }

    public function urlAt(Opportunity $opportunity, int $index): ?string
    {
        return $this->list($opportunity)[$index]['url'] ?? null;
    }

    /**
     * Pull the solicitation documents for an opportunity that arrived without
     * them. When a SAM.gov notice id can be resolved for the record, fetch the
     * official SAM record and merge its resourceLinks into raw_source_data so
     * list() surfaces real, downloadable files.
     *
     * Best-effort and cached: a notice we can't match (or that genuinely has no
     * attachments) is remembered for a day so we don't re-hit SAM on every view.
     * A transient failure (SAM throttled/unavailable) is NEVER cached as a miss —
     * only briefly backed off — so the documents still get pulled once SAM's
     * daily quota resets. Pass $force to bypass the cache (the nightly backfill).
     *
     * Two caches, deliberately separate so a transient failure never looks like a
     * confirmed "no documents":
     *   opp_docs_none:{id}    — SAM confirmed the notice has no attachments (7 days)
     *   opp_docs_backoff:{id} — transient (throttled / not yet resolvable); retry soon
     *
     * The "none" cache also lets the daily "pull for all" backfill converge: a
     * notice confirmed empty isn't re-probed every night, only once a week (in case
     * an amendment later adds files), so quota goes to notices not yet checked.
     *
     * @return string One of: have, none, none_cached, pending, unresolved, throttled, error, pulled.
     */
    public function ensure(Opportunity $opportunity, bool $force = false): string
    {
        if ($this->rawLinks($opportunity) !== []) {
            return 'have'; // already has documents
        }

        $noneKey = "opp_docs_none:{$opportunity->id}";
        $backoffKey = "opp_docs_backoff:{$opportunity->id}";
        if (! $force) {
            if (Cache::has($noneKey)) {
                return 'none_cached'; // already confirmed empty — skip, no API call
            }
            if (Cache::has($backoffKey)) {
                return 'pending';     // couldn't check yet — not "none", just not fetched
            }
        }

        $noticeId = $this->resolveSamNoticeId($opportunity);
        if ($noticeId === null) {
            Cache::put($backoffKey, true, now()->addDay());

            return 'unresolved';
        }

        try {
            $dto = $this->sam->fetchOpportunity($noticeId, $opportunity->posted_date);
        } catch (SamThrottledException) {
            // SAM's quota is exhausted or it's briefly down. Back off for a short
            // window (not a full day) and DON'T record a permanent miss, so the
            // opportunity is retried after the quota resets.
            Cache::put($backoffKey, true, now()->addMinutes(30));

            return 'throttled';
        } catch (\Throwable) {
            return 'error'; // transient — retry on a later view / run, don't cache
        }

        $raw = (array) ($dto?->rawData ?? []);
        $resourceLinks = array_values(array_filter(
            (array) ($raw['resourceLinks'] ?? []),
            fn ($u) => is_string($u) && trim($u) !== '',
        ));
        $links = array_values(array_filter(
            (array) ($raw['links'] ?? []),
            fn ($l) => is_array($l) && ! empty($l['href']) && ($l['rel'] ?? '') !== 'self',
        ));

        if ($resourceLinks === [] && $links === []) {
            Cache::put($noneKey, true, now()->addDays(7));

            return 'none';
        }

        $merged = (array) ($opportunity->raw_source_data ?? []);
        if ($resourceLinks !== []) {
            $merged['resourceLinks'] = $resourceLinks;
        }
        if ($links !== []) {
            $merged['links'] = $links;
        }
        $opportunity->raw_source_data = $merged;

        // Lock in the resolved notice id so sam_url deep-links to the notice.
        if ($opportunity->source?->value === 'sam_gov' && ! $opportunity->external_id) {
            $opportunity->external_id = $noticeId;
        }

        $opportunity->saveQuietly();
        Cache::forget($backoffKey);

        return 'pulled';
    }

    /**
     * Fetch a document from SAM.gov, attaching the API key. Returns the raw
     * body plus content metadata, or null on failure.
     *
     * @return array{body:string,mime:string,filename:string}|null
     */
    public function fetch(string $url): ?array
    {
        try {
            $response = Http::timeout(25)->get($this->withApiKey($url));
        } catch (\Throwable) {
            return null;
        }

        if ($response->failed()) {
            return null;
        }

        $mime = $response->header('Content-Type') ?: 'application/octet-stream';
        // Content-Type can include a charset; keep just the media type.
        $mime = trim(explode(';', $mime)[0]);

        return [
            'body' => $response->body(),
            'mime' => $mime ?: 'application/octet-stream',
            'filename' => $this->filenameFromResponse($response) ?: $this->nameFor($url, 0),
        ];
    }

    /**
     * The SAM.gov notice id for an opportunity, however we can get it: a SAM
     * record carries it directly; other sources (e.g. BidPrime) often link to
     * the original SAM notice in their raw data, and as a last resort we resolve
     * it from the solicitation number against SAM's search index.
     */
    private function resolveSamNoticeId(Opportunity $opportunity): ?string
    {
        if ($opportunity->source?->value === 'sam_gov' && $opportunity->external_id) {
            return (string) $opportunity->external_id;
        }

        $raw = (array) ($opportunity->raw_source_data ?? []);
        $candidateUrls = array_filter([
            $opportunity->source_url,
            $raw['source_url'] ?? null,
            $raw['sourceUrl'] ?? null,
            $raw['url'] ?? null,
        ], 'is_string');

        foreach ($candidateUrls as $url) {
            // Matches both sam.gov/opp/{id}/view and sam.gov/workspace/contract/opp/{id}/view.
            if (preg_match('#sam\.gov/(?:[a-z/]*?)opp/([A-Za-z0-9]+)/view#i', $url, $m)) {
                return $m[1];
            }
        }

        if ($opportunity->solicitation_number) {
            try {
                return $this->samLinks->noticeIdForSolicitation((string) $opportunity->solicitation_number, timeout: 10);
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }

    /**
     * Document URLs already present on the opportunity's raw source record.
     *
     * @return array<int,string>
     */
    private function rawLinks(Opportunity $opportunity): array
    {
        $raw = (array) ($opportunity->raw_source_data ?? []);
        $urls = [];

        foreach ((array) ($raw['resourceLinks'] ?? []) as $url) {
            if (is_string($url) && trim($url) !== '') {
                $urls[] = trim($url);
            }
        }

        // Some records expose files via a "links" array of {rel, href} objects.
        foreach ((array) ($raw['links'] ?? []) as $link) {
            if (is_array($link) && !empty($link['href']) && ($link['rel'] ?? '') !== 'self') {
                $urls[] = (string) $link['href'];
            }
        }

        return array_values(array_unique($urls));
    }

    private function withApiKey(string $url): string
    {
        $key = config('integrations.sam_gov.api_key');
        if (!$key || str_contains($url, 'api_key=')) {
            return $url;
        }
        return $url . (str_contains($url, '?') ? '&' : '?') . 'api_key=' . urlencode($key);
    }

    private function filenameFromResponse($response): ?string
    {
        $disposition = $response->header('Content-Disposition');
        if ($disposition && preg_match('/filename\*?=(?:UTF-8\'\')?"?([^";]+)"?/i', $disposition, $m)) {
            return trim(urldecode($m[1]));
        }
        return null;
    }

    private function nameFor(string $url, int $i): string
    {
        $base = basename((string) parse_url($url, PHP_URL_PATH));
        // SAM resource URLs end in ".../files/{uuid}/download" — no real filename.
        // The actual filename is resolved from the Content-Disposition header at
        // download time; here we just give a clean, ordered label.
        if ($base !== '' && $base !== 'download' && str_contains($base, '.')) {
            return $base;
        }
        return 'Attachment ' . ($i + 1);
    }
}
