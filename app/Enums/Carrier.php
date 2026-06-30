<?php

namespace App\Enums;

/**
 * Shipping carriers Shipments can track. UPS is live; FedEx and DHL are wired
 * for later (the tracking factory returns an "unsupported" client until their
 * integrations land), so adding them is a new client class — not a refactor.
 */
enum Carrier: string
{
    case Ups = 'ups';
    case JbHunt = 'jbhunt';
    case RlCarriers = 'rlcarriers';
    case Fedex = 'fedex';
    case Dhl = 'dhl';

    public function label(): string
    {
        return match ($this) {
            self::Ups => 'UPS',
            self::JbHunt => 'J.B. Hunt',
            self::RlCarriers => 'R+L Carriers',
            self::Fedex => 'FedEx',
            self::Dhl => 'DHL',
        };
    }

    /** Whether a real tracking integration exists for this carrier today. */
    public function supported(): bool
    {
        return in_array($this, [self::Ups, self::JbHunt, self::RlCarriers], true);
    }

    public function color(): string
    {
        return match ($this) {
            self::Ups => 'amber',
            self::JbHunt => 'green',
            self::RlCarriers => 'blue',
            self::Fedex => 'indigo',
            self::Dhl => 'red',
        };
    }

    public function trackingUrl(string $trackingNumber, ?string $referenceType = null): string
    {
        return match ($this) {
            self::Ups => 'https://www.ups.com/track?loc=en_US&tracknum='.$trackingNumber,
            // J.B. Hunt's tracker auto-loads a shipment when both query params are
            // present: k = reference type, v = the number. The #anchor scrolls to
            // the result form. The type is per-shipment (order number, BOL, etc.);
            // default to J.B. Hunt's own default when unset.
            self::JbHunt => 'https://www.jbhunt.com/track-shipments/?k='
                .($referenceType ?: \App\Enums\JbHuntReferenceType::Default->value)
                .'&v='.rawurlencode($trackingNumber).'#TrackShipmentForm',
            // R+L's tracing page deep-links to results when the PRO is passed as a
            // query param (docType=PRO is the default reference; source=web mirrors
            // the site's own form submit).
            self::RlCarriers => 'https://www.rlcarriers.com/freight/shipping/shipment-tracing?pro='
                .rawurlencode($trackingNumber).'&docType=PRO&source=web',
            self::Fedex => 'https://www.fedex.com/fedextrack/?trknbr='.$trackingNumber,
            self::Dhl => 'https://www.dhl.com/en/express/tracking.html?AWB='.$trackingNumber,
        };
    }

    /**
     * The carrier's account login page — a sensible default shown on the Carriers
     * page so staff can jump to where they sign in. Orgs can override it with their
     * own URL (stored per carrier in Organization.settings via CarrierProfileService).
     */
    public function loginUrl(): ?string
    {
        return match ($this) {
            self::Ups => 'https://www.ups.com/lasso/login',
            self::JbHunt => 'https://www.jbhunt.com/account',
            self::RlCarriers => 'https://www.rlcarriers.com/login',
            self::Fedex => 'https://www.fedex.com/secure-login/en-us/',
            self::Dhl => 'https://mydhl.express.dhl/',
        };
    }
}
