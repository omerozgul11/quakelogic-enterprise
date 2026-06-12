<?php

namespace App\Enums;

/**
 * The physical state of a mailed shipment, derived from the UPS Tracking API.
 * On-time-ness vs the deadline is a SEPARATE, computed concern — see
 * App\Enums\DeliveryRisk and MailingTrackingService.
 */
enum MailingStatus: string
{
    case LabelCreated = 'label_created';
    case InTransit = 'in_transit';
    case OutForDelivery = 'out_for_delivery';
    case Delivered = 'delivered';
    case Exception = 'exception';
    case Returned = 'returned';

    public function label(): string
    {
        return match ($this) {
            self::LabelCreated => 'Label created',
            self::InTransit => 'In transit',
            self::OutForDelivery => 'Out for delivery',
            self::Delivered => 'Delivered',
            self::Exception => 'Exception',
            self::Returned => 'Returned to sender',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::LabelCreated => 'gray',
            self::InTransit => 'blue',
            self::OutForDelivery => 'indigo',
            self::Delivered => 'green',
            self::Exception => 'red',
            self::Returned => 'amber',
        };
    }

    public function isDelivered(): bool
    {
        return $this === self::Delivered;
    }

    /** No further polling needed once the shipment reaches a final state. */
    public function isTerminal(): bool
    {
        return in_array($this, [self::Delivered, self::Returned], true);
    }

    /**
     * Map a UPS package status code to our status. UPS uses single-letter
     * current-status codes on the Tracking API (e.g. I = in transit,
     * D = delivered, X = exception, M = manifest/label, O = out for delivery).
     */
    public static function fromUpsCode(?string $code): self
    {
        return match (strtoupper((string) $code)) {
            'M', 'MP' => self::LabelCreated,
            'I', 'P', 'DO', 'DD' => self::InTransit,
            'O', 'OFD' => self::OutForDelivery,
            'D', 'DL' => self::Delivered,
            'X', 'XP' => self::Exception,
            'RS', 'RTS' => self::Returned,
            default => self::InTransit,
        };
    }
}
