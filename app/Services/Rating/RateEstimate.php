<?php

namespace App\Services\Rating;

/**
 * The result of a rate-card estimate: the chosen zone, the billable weight, the
 * published rate, the contract discount, any premium add-on, and the net price —
 * plus enough breakdown for the UI to show how the number was reached.
 */
final class RateEstimate
{
    /** @param array<int,string> $warnings */
    public function __construct(
        public readonly string $zone,
        public readonly string $band,
        public readonly float $actualWeightLb,
        public readonly float $volumetricWeightLb,
        public readonly float $billableWeightLb,
        public readonly ?string $weightKey,
        public readonly float $publishedAmount,
        public readonly float $discountPct,
        public readonly float $discountAmount,
        public readonly ?string $premiumKey,
        public readonly float $premiumAmount,
        public readonly float $netAmount,
        public readonly string $currency,
        public readonly string $serviceLevel,
        public readonly ?int $transitDays,
        public readonly ?float $perLbRate,
        public readonly array $warnings = [],
    ) {}

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'zone' => $this->zone,
            'band' => $this->band,
            'actual_weight_lb' => $this->actualWeightLb,
            'volumetric_weight_lb' => $this->volumetricWeightLb,
            'billable_weight_lb' => $this->billableWeightLb,
            'weight_key' => $this->weightKey,
            'published_amount' => $this->publishedAmount,
            'discount_pct' => $this->discountPct,
            'discount_amount' => $this->discountAmount,
            'premium_key' => $this->premiumKey,
            'premium_amount' => $this->premiumAmount,
            'net_amount' => $this->netAmount,
            'currency' => $this->currency,
            'service_level' => $this->serviceLevel,
            'transit_days' => $this->transitDays,
            'per_lb_rate' => $this->perLbRate,
            'warnings' => $this->warnings,
        ];
    }
}
