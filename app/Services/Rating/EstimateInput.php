<?php

namespace App\Services\Rating;

/**
 * Normalised inputs for a single rate estimate. Holds the raw package figures and
 * derives actual / volumetric (dimensional) weight in pounds — the rest of the
 * calculation lives in {@see RateEstimationService} and stays pure.
 */
final class EstimateInput
{
    private const LB_PER_KG = 2.2046226218;

    /** DHL dimensional-weight divisors: in³ → lb, and cm³ → kg. */
    private const DIM_DIVISOR_IN = 139.0;
    private const DIM_DIVISOR_CM = 5000.0;

    public function __construct(
        public readonly string $originCountry,
        public readonly string $destCountry,
        public readonly float $weight,
        public readonly string $weightUnit = 'lb',
        public readonly ?float $length = null,
        public readonly ?float $width = null,
        public readonly ?float $height = null,
        public readonly string $dimUnit = 'in',
        public readonly string $contentType = 'package',  // package | document
        public readonly ?float $discountPct = null,        // 0..1
        public readonly ?string $premium = null,           // '9' | '12' | null
    ) {}

    public function actualLb(): float
    {
        $w = max(0.0, $this->weight);

        return $this->weightUnit === 'kg' ? $w * self::LB_PER_KG : $w;
    }

    /** Volumetric weight in lb from the dimensions, or 0 when any dimension is missing. */
    public function volumetricLb(): float
    {
        if (! $this->length || ! $this->width || ! $this->height) {
            return 0.0;
        }

        $volume = $this->length * $this->width * $this->height;
        if ($volume <= 0) {
            return 0.0;
        }

        if ($this->dimUnit === 'cm') {
            return ($volume / self::DIM_DIVISOR_CM) * self::LB_PER_KG;
        }

        return $volume / self::DIM_DIVISOR_IN;
    }
}
