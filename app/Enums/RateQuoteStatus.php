<?php

namespace App\Enums;

/**
 * Lifecycle of a shipment rate / spot-price quote. A quote starts as a draft
 * (lane + package captured), becomes "requested" once sent to the carrier,
 * "quoted" when a price comes back, then "expired" or "declined".
 */
enum RateQuoteStatus: string
{
    case Draft = 'draft';
    case Requested = 'requested';
    case Quoted = 'quoted';
    case Expired = 'expired';
    case Declined = 'declined';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Requested => 'Requested',
            self::Quoted => 'Quoted',
            self::Expired => 'Expired',
            self::Declined => 'Declined',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Requested => 'blue',
            self::Quoted => 'green',
            self::Expired => 'amber',
            self::Declined => 'red',
        };
    }
}
