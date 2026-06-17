<?php

namespace App\Services\Reporting;

use App\Support\Currency;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Daily USD exchange rates for the dashboard. Pulls once a day from a free,
 * no-key FX feed (frankfurter.app / European Central Bank) and caches the result
 * for the day. On any failure — or when disabled (tests) — it falls back to the
 * static reference rates in App\Support\Currency, so the dashboard always has a
 * value and never blocks on the network. Returned rates are expressed as USD per
 * one unit of each currency (e.g. EUR 1.09 → "€1 = $1.09").
 */
class ExchangeRateService
{
    /** Currencies shown on the dashboard, EUR first (the headline rate). */
    public const CODES = ['EUR', 'GBP', 'CAD', 'AUD', 'JPY', 'CNY'];

    /**
     * @return array{date:string,source:string,rates:array<int,array{code:string,name:string,symbol:string,usd_per_unit:float}>}
     */
    public function dailyRates(): array
    {
        $cacheKey = 'fx:usd:' . now()->toDateString();

        if (is_array($cached = Cache::get($cacheKey))) {
            return $cached;
        }

        if (config('services.exchange_rates.enabled', true) && ($live = $this->fetchLive()) !== null) {
            // Cache only successful live pulls; a failure stays uncached so the
            // next request retries rather than serving stale fallback all day.
            Cache::put($cacheKey, $live, now()->addHours(12));

            return $live;
        }

        return $this->reference();
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
