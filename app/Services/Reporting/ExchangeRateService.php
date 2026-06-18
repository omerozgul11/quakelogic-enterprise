<?php

namespace App\Services\Reporting;

use App\Support\Currency;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * USD exchange rates for the dashboard. Refreshed on a schedule into a daily
 * cache the dashboard reads instantly. Source chain, best first: near-real-time
 * market quotes (Yahoo Finance, free + no key), then the frankfurter.app / ECB
 * daily reference feed, then the static reference rates in App\Support\Currency.
 * Each step falls through to the next on failure — and the whole thing is
 * disabled in tests — so the dashboard always has a value and never blocks on
 * the network. Returned rates are USD per one unit of each currency (e.g.
 * EUR 1.09 → "€1 = $1.09").
 */
class ExchangeRateService
{
    /** Currencies shown on the dashboard, EUR first (the headline rate). */
    public const CODES = ['EUR', 'GBP', 'CAD', 'AUD', 'JPY', 'CNY'];

    /** Browser-like UA — Yahoo's endpoint rejects requests without one. */
    private const USER_AGENT = 'Mozilla/5.0 (compatible; QuakeLogicEnterprise/1.0)';

    /**
     * @return array{date:string,source:string,rates:array<int,array{code:string,name:string,symbol:string,usd_per_unit:float}>}
     */
    public function dailyRates(): array
    {
        if (is_array($cached = Cache::get('fx:usd:' . now()->toDateString()))) {
            return $cached;
        }

        return $this->refresh();
    }

    /**
     * Re-fetch rates, bypassing the cached copy, and store them. The scheduled
     * refresh calls this every minute so the dashboard's cached value tracks the
     * live market. Tries real-time quotes first, then the ECB daily feed; a total
     * failure stays uncached (returns the static reference) so the next run
     * retries rather than serving a stale fallback for the rest of the day.
     *
     * @return array{date:string,source:string,fetched_at:string,rates:array<int,array{code:string,name:string,symbol:string,usd_per_unit:float}>}
     */
    public function refresh(): array
    {
        if (config('services.exchange_rates.enabled', true)) {
            $fresh = $this->fetchRealtime() ?? $this->fetchLive();
            if ($fresh !== null) {
                Cache::put('fx:usd:' . now()->toDateString(), $fresh, now()->addHours(12));

                return $fresh;
            }
        }

        return $this->reference();
    }

    /**
     * Near-real-time market quotes from Yahoo Finance's chart endpoint (free, no
     * key). One request per currency — for "{CODE}USD=X", meta.regularMarketPrice
     * is already USD per one unit — pooled so all six resolve together. Requires
     * at least the headline EUR rate to be live; any minor pair that's missing is
     * filled from the static reference so the grid is always complete. Returns
     * null on any failure so the caller falls through to the ECB daily feed.
     *
     * @return array<string,mixed>|null
     */
    private function fetchRealtime(): ?array
    {
        if (! config('services.exchange_rates.realtime_enabled', true)) {
            return null;
        }

        $base = rtrim((string) config('services.exchange_rates.realtime_base_url'), '/');
        $timeout = (int) config('services.exchange_rates.timeout', 4);

        try {
            $responses = Http::pool(fn (Pool $pool) => array_map(
                fn (string $code) => $pool->as($code)
                    ->timeout($timeout)
                    ->withHeaders(['User-Agent' => self::USER_AGENT])
                    ->get("{$base}/v8/finance/chart/{$code}USD=X", ['interval' => '1d', 'range' => '1d']),
                self::CODES,
            ));
        } catch (\Throwable $e) {
            Log::warning('Real-time exchange-rate fetch failed; using daily feed.', ['error' => $e->getMessage()]);

            return null;
        }

        $prices = [];
        foreach (self::CODES as $code) {
            $response = $responses[$code] ?? null;
            if (! $response instanceof Response || ! $response->successful()) {
                continue;
            }
            $price = data_get($response->json(), 'chart.result.0.meta.regularMarketPrice');
            if (is_numeric($price) && (float) $price > 0) {
                $prices[$code] = round((float) $price, 6);
            }
        }

        // Without the headline EUR rate this isn't a usable real-time pull; let
        // the ECB daily feed (consistent across all codes) take over instead.
        if (! isset($prices['EUR'])) {
            return null;
        }

        return [
            'date' => now()->toDateString(),
            'source' => 'realtime',
            'fetched_at' => now()->toIso8601String(),
            'rates' => array_map(
                fn (string $code) => $this->row($code, $prices[$code] ?? Currency::rate($code)),
                self::CODES,
            ),
        ];
    }

    /** @return array<string,mixed>|null */
    private function fetchLive(): ?array
    {
        try {
            $response = Http::timeout((int) config('services.exchange_rates.timeout', 4))
                ->get(rtrim((string) config('services.exchange_rates.base_url'), '/') . '/latest', [
                    'from' => 'USD',
                    'to' => implode(',', self::CODES),
                ]);

            if (! $response->successful()) {
                return null;
            }

            $json = $response->json();
            $perUsd = $json['rates'] ?? null; // units of each currency per 1 USD
            if (! is_array($perUsd)) {
                return null;
            }

            $rates = [];
            foreach (self::CODES as $code) {
                $value = $perUsd[$code] ?? null;
                if (! is_numeric($value) || (float) $value <= 0) {
                    continue;
                }
                $rates[] = $this->row($code, round(1 / (float) $value, 4));
            }

            if ($rates === []) {
                return null;
            }

            return [
                'date' => is_string($json['date'] ?? null) ? $json['date'] : now()->toDateString(),
                'source' => 'live',
                'fetched_at' => now()->toIso8601String(),
                'rates' => $rates,
            ];
        } catch (\Throwable $e) {
            Log::warning('Exchange-rate fetch failed; using reference rates.', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /** @return array<string,mixed> */
    private function reference(): array
    {
        return [
            'date' => now()->toDateString(),
            'source' => 'reference',
            'fetched_at' => now()->toIso8601String(),
            'rates' => array_map(fn (string $code) => $this->row($code, Currency::rate($code)), self::CODES),
        ];
    }

    /** @return array{code:string,name:string,symbol:string,usd_per_unit:float} */
    private function row(string $code, float $usdPerUnit): array
    {
        return [
            'code' => $code,
            'name' => Currency::name($code),
            'symbol' => Currency::symbol($code),
            'usd_per_unit' => $usdPerUnit,
        ];
    }
}
