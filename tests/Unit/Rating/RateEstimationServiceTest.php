<?php

namespace Tests\Unit\Rating;

use App\Services\Rating\DhlRateCard;
use App\Services\Rating\EstimateInput;
use App\Services\Rating\RateEstimationException;
use App\Services\Rating\RateEstimationService;
use Tests\TestCase;

/**
 * Pure-computation tests for the DHL rate-card estimator. No DB — boots the app
 * only so the card JSON resolves via resource_path(). Expected figures are read
 * straight off the contract card (docs/dhl-express-rate-sheet-2026.pdf).
 */
class RateEstimationServiceTest extends TestCase
{
    private function service(): RateEstimationService
    {
        DhlRateCard::flush();

        return new RateEstimationService(new DhlRateCard());
    }

    public function test_package_uses_standard_band_and_applies_contract_discount(): void
    {
        $e = $this->service()->estimate(new EstimateInput(
            originCountry: 'US', destCountry: 'GB', weight: 5, discountPct: 0.40,
        ));

        $this->assertSame('C', $e->zone);              // United Kingdom → zone C
        $this->assertSame('standard', $e->band);
        $this->assertSame(5.0, $e->billableWeightLb);
        $this->assertEqualsWithDelta(262.54, $e->publishedAmount, 0.001);   // standard 5.0 lb, zone C
        $this->assertEqualsWithDelta(157.52, $e->netAmount, 0.001);         // less 40%
    }

    public function test_over_150lb_uses_per_pound_multiplier(): void
    {
        $e = $this->service()->estimate(new EstimateInput(
            originCountry: 'US', destCountry: 'GB', weight: 200, discountPct: 0.40,
        ));

        $this->assertSame('multiplier', $e->band);
        $this->assertSame(17.45, $e->perLbRate);                    // 150.1–330 band, zone C
        $this->assertEqualsWithDelta(3490.00, $e->publishedAmount, 0.001);  // 17.45 × 200
        $this->assertEqualsWithDelta(2094.00, $e->netAmount, 0.001);
    }

    public function test_dimensional_weight_overrides_actual(): void
    {
        $e = $this->service()->estimate(new EstimateInput(
            originCountry: 'US', destCountry: 'GB', weight: 5,
            length: 12, width: 12, height: 12, discountPct: 0,
        ));

        // 12³ / 139 = 12.43 lb → rounds up to 13.
        $this->assertSame(13.0, $e->billableWeightLb);
        $this->assertEqualsWithDelta(361.21, $e->publishedAmount, 0.001);   // standard 13.0 lb, zone C
        $this->assertNotEmpty($e->warnings);
    }

    public function test_document_envelope_and_document_bands(): void
    {
        $envelope = $this->service()->estimate(new EstimateInput(
            originCountry: 'US', destCountry: 'DE', weight: 0.5, contentType: 'document', discountPct: 0,
        ));
        $this->assertSame('envelope', $envelope->band);
        $this->assertEqualsWithDelta(83.52, $envelope->publishedAmount, 0.001);  // envelope 0.6 lb, zone C

        $document = $this->service()->estimate(new EstimateInput(
            originCountry: 'US', destCountry: 'FR', weight: 1, contentType: 'document', discountPct: 0,
        ));
        $this->assertSame('document', $document->band);
        $this->assertEqualsWithDelta(118.54, $document->publishedAmount, 0.001);  // documents 1.0 lb, zone C
    }

    public function test_premium_is_added_after_the_discount(): void
    {
        $e = $this->service()->estimate(new EstimateInput(
            originCountry: 'US', destCountry: 'GB', weight: 5, discountPct: 0.60, premium: '9',
        ));

        // 262.54 × (1 − 0.60) = 105.02, plus the 25.20 premium add-on.
        $this->assertEqualsWithDelta(25.20, $e->premiumAmount, 0.001);
        $this->assertEqualsWithDelta(130.22, $e->netAmount, 0.001);
    }

    public function test_kilograms_are_converted_to_pounds(): void
    {
        $e = $this->service()->estimate(new EstimateInput(
            originCountry: 'US', destCountry: 'GB', weight: 1, weightUnit: 'kg', discountPct: 0,
        ));

        // 1 kg = 2.2046 lb → rounds up to 3.
        $this->assertSame(3.0, $e->billableWeightLb);
    }

    public function test_unknown_destination_country_is_rejected(): void
    {
        $this->expectException(RateEstimationException::class);
        $this->service()->estimate(new EstimateInput(originCountry: 'US', destCountry: 'ZZ', weight: 5));
    }

    public function test_non_us_origin_is_rejected(): void
    {
        $this->expectException(RateEstimationException::class);
        $this->service()->estimate(new EstimateInput(originCountry: 'CA', destCountry: 'GB', weight: 5));
    }

    public function test_missing_weight_is_rejected(): void
    {
        $this->expectException(RateEstimationException::class);
        $this->service()->estimate(new EstimateInput(originCountry: 'US', destCountry: 'GB', weight: 0));
    }
}
