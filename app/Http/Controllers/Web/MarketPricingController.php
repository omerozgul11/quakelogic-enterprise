<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\BidSources\MarketAwardsService;
use App\Services\BidSources\SamGov\SamGovConnector;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Market pricing benchmarks: browse past awarded contracts (with award amounts)
 * from SAM.gov so writers can sanity-check pricing against similar projects.
 */
class MarketPricingController extends Controller
{
    public function __construct(private readonly SamGovConnector $connector) {}

    public function index(Request $request, MarketAwardsService $marketAwards): Response
    {
        $filters = $request->validate([
            'keyword' => 'nullable|string|max:120',
            'naics' => 'nullable|string|max:20',
        ]);

        $searched = $request->filled('keyword') || $request->filled('naics');
        $awards = [];
        $feedKeywords = [];
        $refreshedAt = null;

        if ($searched) {
            try {
                $awards = $this->connector->searchAwards(array_filter([
                    'keyword' => $filters['keyword'] ?? null,
                    'naicsCode' => $filters['naics'] ?? null,
                    'limit' => 100,
                ]));
            } catch (\Throwable $e) {
                Log::warning('Market pricing search failed', ['error' => $e->getMessage()]);
            }

            // Highest-value awards first; awards without a disclosed amount go last.
            usort($awards, fn ($a, $b) => ($b['amount'] ?? -1) <=> ($a['amount'] ?? -1));
        } else {
            // No search: show the cached feed of recent awards in the user's
            // focus areas (kept fresh by the background sync), newest first.
            $feed = $marketAwards->recent($request->user());
            $awards = $feed['awards'];
            $feedKeywords = $feed['keywords'];
            $refreshedAt = $feed['refreshed_at'];
        }

        $withAmount = array_values(array_filter($awards, fn ($a) => ($a['amount'] ?? 0) > 0));
        $stats = [
            'count' => count($awards),
            'priced' => count($withAmount),
            'median' => $this->median(array_map(fn ($a) => (float) $a['amount'], $withAmount)),
            'max' => $withAmount ? max(array_map(fn ($a) => (float) $a['amount'], $withAmount)) : null,
            'min' => $withAmount ? min(array_map(fn ($a) => (float) $a['amount'], $withAmount)) : null,
        ];

        return Inertia::render('MarketPricing/Index', [
            'awards' => $awards,
            'filters' => $filters,
            'searched' => $searched,
            'stats' => $stats,
            'feedKeywords' => $feedKeywords,
            'personalKeywords' => array_values($request->user()->market_keywords ?? []),
            'refreshedAt' => $refreshedAt,
            'connected' => $this->connector->isConfigured(),
        ]);
    }

    /**
     * Save a private focus-area keyword for the current user and rebuild
     * their personal feed in the background.
     */
    public function storeKeyword(Request $request, MarketAwardsService $marketAwards): RedirectResponse
    {
        $data = $request->validate(['keyword' => 'required|string|max:40']);
        $user = $request->user();
        $kw = trim($data['keyword']);

        $list = $user->market_keywords ?? [];
        $taken = array_map('mb_strtolower', array_merge($list, $marketAwards->presavedKeywords($user->organization_id)));
        if ($kw !== '' && !in_array(mb_strtolower($kw), $taken, true)) {
            $list[] = $kw;
            $user->update(['market_keywords' => array_values($list)]);
            app()->terminating(fn () => $marketAwards->refresh($user));

            return back(303)->with('success', 'Focus area saved — pulling matching awards in the background.');
        }

        return back(303);
    }

    /** Remove one of the current user's private focus-area keywords. */
    public function destroyKeyword(Request $request, MarketAwardsService $marketAwards): RedirectResponse
    {
        $data = $request->validate(['keyword' => 'required|string']);
        $user = $request->user();

        $list = array_values(array_filter(
            $user->market_keywords ?? [],
            fn ($k) => mb_strtolower($k) !== mb_strtolower($data['keyword'])
        ));
        $user->update(['market_keywords' => $list]);
        app()->terminating(fn () => $marketAwards->refresh($user));

        return back(303);
    }

    private function median(array $values): ?float
    {
        if (!$values) {
            return null;
        }
        sort($values);
        $n = count($values);
        $mid = intdiv($n, 2);
        return $n % 2 ? $values[$mid] : ($values[$mid - 1] + $values[$mid]) / 2;
    }
}
