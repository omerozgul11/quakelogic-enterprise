<?php

namespace App\Services\BidSources;

use App\Models\Opportunity;
use Illuminate\Support\Facades\Http;

/**
 * Surfaces the solicitation documents an opportunity carries from its source
 * feed (SAM.gov). SAM's Opportunities API v2 exposes downloadable files under
 * "resourceLinks" in the raw record; we never store the files themselves, so
 * previews/downloads are proxied on demand (with the API key kept server-side).
 *
 * In environments where SAM.gov sync is disabled, opportunities carry no
 * resource links and list() returns an empty array — the UI shows an empty
 * state until real syncing is enabled.
 */
class OpportunityDocumentService
{
    /**
     * The downloadable documents for an opportunity.
     *
     * @return array<int,array{index:int,name:string,url:string}>
     */
    public function list(Opportunity $opportunity): array
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

        $urls = array_values(array_unique($urls));

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
