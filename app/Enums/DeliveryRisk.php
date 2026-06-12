<?php

namespace App\Enums;

/**
 * On-time evaluation of a mailing vs its deadline — computed from the shipment
 * status + scheduled/actual delivery, never stored as the primary state.
 */
enum DeliveryRisk: string
{
    case OnTrack = 'on_track';
    case AtRisk = 'at_risk';
    case DeliveredOnTime = 'delivered_on_time';
    case DeliveredLate = 'delivered_late';
    case Exception = 'exception';
    case Unknown = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::OnTrack => 'On track',
            self::AtRisk => 'At risk',
            self::DeliveredOnTime => 'Delivered on time',
            self::DeliveredLate => 'Delivered late',
            self::Exception => 'Exception',
            self::Unknown => 'Unknown',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::OnTrack => 'green',
            self::AtRisk => 'amber',
            self::DeliveredOnTime => 'green',
            self::DeliveredLate => 'red',
            self::Exception => 'red',
            self::Unknown => 'gray',
        };
    }
}
