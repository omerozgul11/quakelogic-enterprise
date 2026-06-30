<?php

namespace App\Services\Rating;

/**
 * Read-only accessor over the parsed DHL Express contract rate card JSON
 * (resources/rate-cards, produced by `rating:build-dhl-card`). Pure lookups only —
 * the band-selection / discount / volumetric-weight rules live in
 * {@see RateEstimationService}. The decoded file is cached per process per path.
 */
class DhlRateCard
{
    /** @var array<string,array<string,mixed>> decoded card keyed by file path */
    private static array $cache = [];

    /** @var array<string,mixed> */
    private array $data;

    public function __construct(?string $path = null)
    {
        $path ??= resource_path('rate-cards/dhl-express-worldwide-2026.json');

        if (! isset(self::$cache[$path])) {
            $json = is_file($path) ? (string) file_get_contents($path) : '';
            self::$cache[$path] = $json !== '' ? (json_decode($json, true) ?: []) : [];
        }

        $this->data = self::$cache[$path];
    }

    /** Forget the in-process cache (tests that swap the card file). */
    public static function flush(): void
    {
        self::$cache = [];
    }

    public function isLoaded(): bool
    {
        return ($this->data['country_zones'] ?? []) !== [] && ($this->data['bands']['standard']['rates'] ?? []) !== [];
    }

    public function currency(): string
    {
        return $this->data['currency'] ?? 'USD';
    }

    public function product(): string
    {
        return $this->data['product'] ?? 'DHL Express Worldwide';
    }

    public function originCountry(): string
    {
        return strtoupper($this->data['origin_country'] ?? 'US');
    }

    public function asOf(): ?string
    {
        return $this->data['as_of'] ?? null;
    }

    /** ISO-2 destination country → zone letter (A–N), or null if not on the card. */
    public function zoneFor(string $country): ?string
    {
        return $this->data['country_zones'][strtoupper(trim($country))] ?? null;
    }

    public function bandMaxLb(string $band): ?float
    {
        $v = $this->data['bands'][$band]['max_lb'] ?? null;

        return $v === null ? null : (float) $v;
    }

    /** Ascending list of weight-row keys (e.g. "1.0") available in a band. */
    public function weightKeys(string $band): array
    {
        $keys = array_keys($this->data['bands'][$band]['rates'] ?? []);
        usort($keys, fn ($a, $b) => (float) $a <=> (float) $b);

        return $keys;
    }

    /** Heaviest row in the standard table (the multiplier takes over above it). */
    public function maxStandardWeight(): float
    {
        $keys = $this->weightKeys('standard');

        return $keys === [] ? 150.0 : (float) end($keys);
    }

    public function rate(string $band, string $weightKey, string $zone): ?float
    {
        $v = $this->data['bands'][$band]['rates'][$weightKey][$zone] ?? null;

        return $v === null ? null : (float) $v;
    }

    /** Per-lb multiplier ($/lb) for shipments over the standard table, by zone. */
    public function multiplierRate(string $zone, float $weight): ?float
    {
        $bands = $this->data['multipliers'] ?? [];
        foreach ($bands as $b) {
            $to = $b['to'] ?? null;
            if ($weight >= (float) ($b['from'] ?? 0) && ($to === null || $weight <= (float) $to)) {
                return isset($b['rates'][$zone]) ? (float) $b['rates'][$zone] : null;
            }
        }

        // Above the last published band: fall back to the heaviest band's rate.
        $last = $bands === [] ? null : end($bands);

        return $last && isset($last['rates'][$zone]) ? (float) $last['rates'][$zone] : null;
    }

    public function premium(string $key): ?float
    {
        $v = $this->data['premiums'][$key] ?? null;

        return $v === null ? null : (float) $v;
    }

    /** @return array<int,array{min:int|float,max:int|float|null,pct:float}> */
    public function discountTiers(string $lane = 'export'): array
    {
        return $this->data['discounts']['dynamic'][$lane] ?? [];
    }

    public function flatDiscount(string $key): ?float
    {
        $v = $this->data['discounts']['flat'][$key] ?? null;

        return $v === null ? null : (float) $v;
    }
}
