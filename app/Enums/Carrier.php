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
    case Fedex = 'fedex';
    case Dhl = 'dhl';

    public function label(): string
    {
        return match ($this) {
            self::Ups => 'UPS',
            self::Fedex => 'FedEx',
            self::Dhl => 'DHL',
        };
    }

    /** Whether a real tracking integration exists for this carrier today. */
    public function supported(): bool
    {
        return $this === self::Ups;
    }

    public function color(): string
    {
        return match ($this) {
            self::Ups => 'amber',
            self::Fedex => 'indigo',
            self::Dhl => 'red',
        };
    }

    public function trackingUrl(string $trackingNumber): string
    {
        return match ($this) {
            self::Ups => 'https://www.ups.com/track?loc=en_US&tracknum='.$trackingNumber,
            self::Fedex => 'https://www.fedex.com/fedextrack/?trknbr='.$trackingNumber,
            self::Dhl => 'https://www.dhl.com/en/express/tracking.html?AWB='.$trackingNumber,
        };
    }
}
