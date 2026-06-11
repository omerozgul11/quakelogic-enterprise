<?php

namespace App\Services\BidSources;

use App\Models\User;
use App\Services\BidSources\SamGov\SamGovConnector;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Cached feeds of recently awarded contracts matching focus keywords, so
 * Market Pricing shows relevant benchmarks without a search. Users who save
 * their own (private) market keywords get a personal feed; everyone else
 * shares the organization feed built from the presaved focus areas. Refreshed
 * by the background pipeline sync and the on-login refresh, on the same
 * cadence as the opportunity pipeline.
 */
class MarketAwardsService
{
    /**
     * Cap on official-API lookups per refresh that backfill award amounts for
     * full-text hits (the full-text endpoint never returns amounts). Lookups
     * are cached per notice, so over a few refresh cycles the whole feed fills
     * in without ever bursting the API.
     */
    private const MAX_DETAIL_LOOKUPS = 20;

    /**
     * Most awards any single keyword can contribute, so a common word like
     * "machine" can't flood the feed and crowd out the niche focus areas.
     */
    private const PER_KEYWORD_CAP = 15;

    public function __construct(
        private readonly SamGovConnector $connector,
        private readonly OpportunityPipelineService $pipeline,
    ) {}

    /**
     * The user's feed, served from cache. On a miss (e.g. they just saved
     * their first personal keyword) the rebuild — many external API calls —
     * runs after the response instead of blocking the page; the shared feed
     * (or an empty one) is served meanwhile.
     *
     * @return array{awards: array<int,array<string,mixed>>, keywords: array<int,string>, refreshed_at: string|null}
     */
    public function recent(User $user): array
    {
        if ($cached = Cache::get($this->cacheKey($user))) {
            return $cached;
        }

        app()->terminating(fn () => $this->refresh($user));

        return Cache::get($this->sharedKey($user->organization_id))
            ?? ['awards' => [], 'keywords' => $this->focusKeywords($user), 'refreshed_at' => null];
    }

    /** Rebuild and cache the feed for this user's focus keywords. Never throws. */
    public function refresh(User $user): array
    {
        return $this->build($this->focusKeywords($user), $this->cacheKey($user));
    }

    /** Rebuild and cache the organization-wide feed (presaved focus areas only). */
    public function refreshShared(int $organizationId): array
    {
        return $this->build($this->presavedKeywords($organizationId), $this->sharedKey($organizationId));
    }

    /**
     * The presaved focus areas every user starts from: the team's pipeline
     * keywords, falling back to the generic defaults when none are saved.
     *
     * @return array<int,string>
     */
    public function presavedKeywords(int $organizationId): array
    {
        $team = $this->pipeline->teamKeywords($organizationId);

        return $team !== [] ? $team : array_slice((array) config('pipeline.keywords', []), 0, 4);
    }

    /**
     * Pull the feed from SAM.gov and cache it: per keyword, an official title
     * search plus a full-text search — niche keywords almost never appear in
     * award titles. Deduped and sorted most-recent first. Never throws; an
     * entirely empty pull keeps the previous feed.
     *
     * @param  array<int,string>  $keywords
     */
    private function build(array $keywords, string $cacheKey): array
    {
        $byDateDesc = fn ($a, $b) => strcmp(
            (string) ($b['award_date'] ?? $b['posted_date'] ?? ''),
            (string) ($a['award_date'] ?? $a['posted_date'] ?? ''),
        );
        $byKey = [];

        foreach ($keywords as $keyword) {
            $found = [];
            try {
                $found = $this->connector->searchAwards(['keyword' => $keyword, 'limit' => 25]);
            } catch (\Throwable $e) {
                Log::warning('Market awards feed pull failed', ['keyword' => $keyword, 'error' => $e->getMessage()]);
            }
            $found = array_merge($found, $this->connector->searchAwardsFullText($keyword, 25));
            usort($found, $byDateDesc);

            $kept = 0;
            foreach ($found as $award) {
                if ($kept >= self::PER_KEYWORD_CAP) {
                    break;
                }
                $key = $this->dedupeKey($award);
                if (isset($byKey[$key])) {
                    continue;
                }
                $award['matched_keyword'] = $keyword;
                $byKey[$key] = $award;
                $kept++;
            }
        }

        $awards = $this->fillMissingAmounts(array_values($byKey));
        usort($awards, $byDateDesc);
        $awards = array_slice($awards, 0, 60);

        if ($awards === [] && ($previous = Cache::get($cacheKey))) {
            return $previous;
        }

        $payload = [
            'awards' => $awards,
            'keywords' => $keywords,
            'refreshed_at' => now()->toIso8601String(),
        ];

        Cache::put($cacheKey, $payload, now()->addDay());

        return $payload;
    }

    /**
     * The same notice arrives with different URL shapes per source (official:
     * /workspace/contract/opp/{id}/view, full-text: /opp/{id}/view), so key on
     * the notice ID embedded in the URL whenever one is present.
     */
    private function dedupeKey(array $award): string
    {
        if (!empty($award['external_id'])) {
            return (string) $award['external_id'];
        }
        if (preg_match('#/opp/([0-9a-f]{10,})/#i', (string) ($award['url'] ?? ''), $m)) {
            return $m[1];
        }

        return $award['solicitation_number']
            ?? (($award['title'] ?? '') . '|' . ($award['agency'] ?? ''));
    }

    /**
     * Backfill award amounts for full-text hits via the official per-notice
     * lookup, capped per refresh and cached per notice so each award is only
     * ever fetched once.
     *
     * @param  array<int,array<string,mixed>>  $awards
     * @return array<int,array<string,mixed>>
     */
    private function fillMissingAmounts(array $awards): array
    {
        $lookups = 0;

        foreach ($awards as &$award) {
            if (($award['amount'] ?? null) !== null || empty($award['external_id'])) {
                continue;
            }

            $detailKey = 'market-awards:detail:' . $award['external_id'];
            $detail = Cache::get($detailKey);

            if ($detail === null && $lookups < self::MAX_DETAIL_LOOKUPS) {
                $lookups++;
                try {
                    $detail = $this->connector->getAward($award['external_id']) ?? ['amount' => null];
                } catch (\Throwable $e) {
                    Log::warning('Award detail lookup failed', ['notice' => $award['external_id'], 'error' => $e->getMessage()]);
                    continue;
                }
                // Awards are historical, but amounts are sometimes published
                // late — re-check amountless notices after a week.
                Cache::put($detailKey, $detail, ($detail['amount'] ?? null) !== null ? now()->addMonths(6) : now()->addWeek());
            }

            if (is_array($detail)) {
                foreach (['amount', 'awardee', 'award_date', 'set_aside', 'naics'] as $field) {
                    $award[$field] = $award[$field] ?? $detail[$field] ?? null;
                }
            }
        }
        unset($award);

        return $awards;
    }

    /**
     * The user's saved (private) market keywords first, then the presaved
     * focus areas, deduped and capped.
     *
     * @return array<int,string>
     */
    private function focusKeywords(User $user): array
    {
        $personal = array_values(array_filter(
            array_map(fn ($k) => trim((string) $k), (array) ($user->market_keywords ?? [])),
            fn ($k) => $k !== '',
        ));

        return collect(array_merge($personal, $this->presavedKeywords($user->organization_id)))
            ->unique(fn ($k) => mb_strtolower($k))
            ->take((int) config('pipeline.keyword_sync_max', 8))
            ->values()
            ->all();
    }

    /** Users with saved keywords get their own feed; everyone else shares the org feed. */
    private function cacheKey(User $user): string
    {
        return ($user->market_keywords ?? []) === []
            ? $this->sharedKey($user->organization_id)
            : "market-awards:feed:user:{$user->id}";
    }

    private function sharedKey(int $organizationId): string
    {
        return "market-awards:feed:{$organizationId}";
    }
}
