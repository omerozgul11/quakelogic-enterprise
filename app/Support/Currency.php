<?php

namespace App\Support;

/**
 * Multi-currency support for proposal/project values.
 *
 * Each proposal stores its amounts in a single `currency`. Per-record screens
 * (proposal show/list/board) display amounts in that native currency, while the
 * executive/personal dashboards normalise everything to USD so company-wide
 * totals are comparable.
 *
 * Rates are static reference rates (USD per 1 unit of the currency). They are
 * intentionally approximate — this platform does not pull a live FX feed. To
 * refresh them, update self::RATES below.
 */
final class Currency
{
    public const DEFAULT = 'USD';

    /** code => [name, symbol, usd_rate] where usd_rate = USD per 1 unit. */
    private const CURRENCIES = [
        'USD' => ['US Dollar', '$', 1.0],
        'EUR' => ['Euro', '€', 1.08],
        'GBP' => ['British Pound', '£', 1.27],
        'CAD' => ['Canadian Dollar', 'C$', 0.73],
        'AUD' => ['Australian Dollar', 'A$', 0.66],
        'JPY' => ['Japanese Yen', '¥', 0.0064],
        'CHF' => ['Swiss Franc', 'CHF', 1.11],
        'CNY' => ['Chinese Yuan', '¥', 0.138],
        'INR' => ['Indian Rupee', '₹', 0.012],
        'MXN' => ['Mexican Peso', 'MX$', 0.058],
        'BRL' => ['Brazilian Real', 'R$', 0.18],
        'SGD' => ['Singapore Dollar', 'S$', 0.74],
        'NZD' => ['New Zealand Dollar', 'NZ$', 0.61],
        'AED' => ['UAE Dirham', 'د.إ', 0.272],
        'SAR' => ['Saudi Riyal', '﷼', 0.266],
        'ZAR' => ['South African Rand', 'R', 0.054],
    ];

    /** @return list<string> */
    public static function codes(): array
    {
        return array_keys(self::CURRENCIES);
    }

    public static function isValid(?string $code): bool
    {
        return $code !== null && isset(self::CURRENCIES[strtoupper($code)]);
    }

    public static function normalize(?string $code): string
    {
        $code = strtoupper((string) $code);
        return isset(self::CURRENCIES[$code]) ? $code : self::DEFAULT;
    }

    public static function name(?string $code): string
    {
        return self::CURRENCIES[self::normalize($code)][0];
    }

    public static function symbol(?string $code): string
    {
        return self::CURRENCIES[self::normalize($code)][1];
    }

    public static function rate(?string $code): float
    {
        return self::CURRENCIES[self::normalize($code)][2];
    }

    /**
     * Convert an amount in the given currency to USD.
     */
    public static function toUsd(float|int|string|null $amount, ?string $code): float
    {
        return round((float) $amount * self::rate($code), 2);
    }

    /**
     * Options for a <Select> on the frontend.
     *
     * @return list<array{value:string,label:string,symbol:string,name:string}>
     */
    public static function options(): array
    {
        $options = [];
        foreach (self::CURRENCIES as $code => [$name, $symbol]) {
            $options[] = [
                'value' => $code,
                'label' => "{$code} — {$name}",
                'symbol' => $symbol,
                'name' => $name,
            ];
        }
        return $options;
    }

    /**
     * A raw SQL fragment that converts a monetary column (or expression) to USD
     * using the per-row `currency` column and the static rate table, e.g.
     *
     *   $q->sum(DB::raw(Currency::usdExpr('proposal_value')))
     *
     * Returns a plain string (not an Expression) so it can be embedded in larger
     * raw selects. Unknown / null currencies are treated as 1:1 (ELSE 1) so
     * totals never silently drop rows.
     */
    public static function usdExpr(string $column): string
    {
        $cases = [];
        foreach (self::CURRENCIES as $code => [, , $rate]) {
            if ($rate == 1.0) {
                continue; // handled by ELSE 1
            }
            // $code is from our own whitelist; $rate is a float we format ourselves.
            $cases[] = sprintf("WHEN '%s' THEN %.6f", $code, $rate);
        }
        $caseSql = 'CASE `currency` ' . implode(' ', $cases) . ' ELSE 1 END';

        return "({$column} * {$caseSql})";
    }
}
