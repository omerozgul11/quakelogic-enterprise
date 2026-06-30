<?php

namespace App\Services\Rating;

/**
 * Computes an up-front shipping estimate from QuakeLogic's DHL Express contract
 * rate card, so the team can price a shipment without emailing DHL for a spot
 * quote. Scope: the Export lane (US-outbound, weight in lb) of DHL Express
 * Worldwide — the primary lane for a US shipper.
 *
 * The calculation is pure (no DB, no I/O beyond the injected card): chargeable
 * weight = max(actual, dimensional) → destination zone → published rate from the
 * right weight band (or the >150 lb per-lb multiplier) → contract discount on the
 * transport charge → plus any premium add-on. Unit-testable like
 * CommissionCalculationService.
 */
class RateEstimationService
{
    /** DHL Express won't accept shipments above this; we still estimate but warn. */
    private const NETWORK_MAX_LB = 6600.0;

    /**
     * Typical DHL Express transit by destination zone, in business days. APPROXIMATE
     * and not part of the contract card — a planning hint only, surfaced as such.
     */
    private const TYPICAL_TRANSIT = [
        'A' => 2, 'B' => 2, 'C' => 3, 'D' => 3, 'E' => 4, 'F' => 3, 'G' => 3,
        'H' => 3, 'I' => 4, 'J' => 5, 'K' => 4, 'L' => 5, 'M' => 4, 'N' => 6,
    ];

    public function __construct(private readonly DhlRateCard $card) {}

    public function estimate(EstimateInput $in): RateEstimate
    {
        if (! $this->card->isLoaded()) {
            throw new RateEstimationException('The DHL rate card is not available.');
        }
        if (strtoupper($in->originCountry) !== $this->card->originCountry()) {
            throw new RateEstimationException('Rate-card estimates cover US-outbound (export) shipments only.');
        }

        $actualLb = $in->actualLb();
        $volumetricLb = $in->volumetricLb();
        $chargeable = max($actualLb, $volumetricLb);
        if ($chargeable <= 0) {
            throw new RateEstimationException('Enter a package weight (or dimensions) to estimate.');
        }

        $zone = $this->card->zoneFor($in->destCountry);
        if ($zone === null) {
            throw new RateEstimationException(
                'No DHL zone is defined for destination "'.strtoupper(trim($in->destCountry)).'". '.
                'Check the country code, or request a spot quote.'
            );
        }

        [$band, $weightKey, $billableLb, $published, $perLb] = $this->lookupRate($zone, $chargeable, $in->contentType);

        $warnings = [];
        if ($billableLb > self::NETWORK_MAX_LB) {
            $warnings[] = 'Above the DHL Express 6,600 lb network maximum — confirm acceptance with DHL.';
        }
        if ($volumetricLb > $actualLb && $volumetricLb > 0) {
            $warnings[] = sprintf(
                'Dimensional weight (%.1f lb) is higher than actual (%.1f lb) and was used as the chargeable weight.',
                $volumetricLb,
                $actualLb,
            );
        }

        $discountPct = $in->discountPct !== null ? max(0.0, min(1.0, $in->discountPct)) : 0.0;
        $discountAmount = round($published * $discountPct, 2);
        $netTransport = round($published - $discountAmount, 2);

        $premiumKey = $in->premium !== null ? 'premium_'.$in->premium : null;
        $premiumAmount = $premiumKey !== null ? ($this->card->premium($premiumKey) ?? 0.0) : 0.0;

        return new RateEstimate(
            zone: $zone,
            band: $band,
            actualWeightLb: round($actualLb, 2),
            volumetricWeightLb: round($volumetricLb, 2),
            billableWeightLb: round($billableLb, 1),
            weightKey: $weightKey,
            publishedAmount: round($published, 2),
            discountPct: $discountPct,
            discountAmount: $discountAmount,
            premiumKey: $in->premium,
            premiumAmount: round($premiumAmount, 2),
            netAmount: round($netTransport + $premiumAmount, 2),
            currency: $this->card->currency(),
            serviceLevel: $this->card->product(),
            transitDays: self::TYPICAL_TRANSIT[$zone] ?? null,
            perLbRate: $perLb,
            warnings: $warnings,
        );
    }

    /**
     * Pick the weight band and published rate for a zone + chargeable weight.
     *
     * @return array{0:string,1:?string,2:float,3:float,4:?float}
     *               band, weightKey (null for multiplier), billableLb, published, perLbRate
     */
    private function lookupRate(string $zone, float $chargeable, string $contentType): array
    {
        $maxStandard = $this->card->maxStandardWeight();

        if ($contentType === 'document') {
            $envMax = $this->card->bandMaxLb('envelope') ?? 0.625;
            if ($chargeable <= $envMax) {
                $key = $this->card->weightKeys('envelope')[0] ?? '0.6';

                return ['envelope', $key, (float) $key, $this->rateOrFail('envelope', $key, $zone), null];
            }

            $docMax = $this->card->bandMaxLb('document') ?? 4.0;
            if ($chargeable <= $docMax) {
                $w = max(1, (int) ceil($chargeable));
                $key = number_format($w, 1, '.', '');

                return ['document', $key, (float) $w, $this->rateOrFail('document', $key, $zone), null];
            }

            // Documents from 5 lb are priced on the standard table.
            return $this->standardOrMultiplier($zone, max(5, (int) ceil($chargeable)), $maxStandard);
        }

        // Packages (non-documents): from 1 lb on the standard table.
        return $this->standardOrMultiplier($zone, max(1, (int) ceil($chargeable)), $maxStandard);
    }

    /** @return array{0:string,1:?string,2:float,3:float,4:?float} */
    private function standardOrMultiplier(string $zone, int $weight, float $maxStandard): array
    {
        if ($weight <= $maxStandard) {
            $key = number_format($weight, 1, '.', '');

            return ['standard', $key, (float) $weight, $this->rateOrFail('standard', $key, $zone), null];
        }

        $perLb = $this->card->multiplierRate($zone, $weight);
        if ($perLb === null) {
            throw new RateEstimationException("No per-pound multiplier is defined for zone {$zone}.");
        }

        return ['multiplier', null, (float) $weight, round($perLb * $weight, 2), $perLb];
    }

    private function rateOrFail(string $band, string $key, string $zone): float
    {
        $rate = $this->card->rate($band, $key, $zone);
        if ($rate === null) {
            throw new RateEstimationException("No {$band} rate for {$key} lb in zone {$zone}.");
        }

        return $rate;
    }
}
