<?php

namespace App\Services\BidSources\SamGov;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Resolves a SAM.gov notice id for a solicitation number via SAM's public UI
 * search (the same index the website uses). Used to back-fill the canonical
 * notice link for older opportunities that were imported without it.
 */
class SamLinkResolver
{
    private const SEARCH_URL = 'https://sam.gov/api/prod/sgs/v1/search/';

    /**
     * Best-matching SAM notice id for a solicitation number, or null when no
     * exact match is found. When a solicitation maps to several notices (e.g. a
     * Sources Sought followed by the actual solicitation), the active /
     * most-recently-modified one wins — that's the live notice.
     */
    public function noticeIdForSolicitation(string $solicitationNumber, int $timeout = 20): ?string
    {
        $solnum = trim($solicitationNumber);
        if ($solnum === '') {
            return null;
        }

        try {
            $response = Http::timeout($timeout)->get(self::SEARCH_URL, [
                'index' => 'opp',
                'q' => $solnum,
                'page' => 0,
                'size' => 20,
                'sort' => '-modifiedDate',
                'mode' => 'search',
            ]);

            if (! $response->successful()) {
                return null;
            }

            $hits = $response->json('_embedded.results', []) ?? [];
        } catch (\Throwable $e) {
            Log::warning('SAM link resolve failed', ['solnum' => $solnum, 'error' => $e->getMessage()]);

            return null;
        }

        // Keep only exact solicitation-number matches — the keyword search is fuzzy.
        $matches = array_values(array_filter($hits, fn ($h) => isset($h['_id'], $h['solicitationNumber'])
            && strcasecmp(trim((string) $h['solicitationNumber']), $solnum) === 0));

        if ($matches === []) {
            return null;
        }

        // Results are sorted most-recent-first; prefer an active notice.
        foreach ($matches as $hit) {
            if (($hit['isActive'] ?? false) === true) {
                return (string) $hit['_id'];
            }
        }

        return (string) $matches[0]['_id'];
    }
}
