<?php

namespace App\Enums\Crm;

/**
 * Lifecycle of a project shipment — equipment moving to (or from) the install
 * site. Independent of any live carrier integration; set manually as the crate
 * is prepared, dispatched and received.
 */
enum ProjectShipmentStatus: string
{
    case Preparing = 'preparing';
    case Ready = 'ready';
    case InTransit = 'in_transit';
    case Delivered = 'delivered';
    case Exception = 'exception';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Preparing => 'Preparing',
            self::Ready => 'Ready to ship',
            self::InTransit => 'In transit',
            self::Delivered => 'Delivered',
            self::Exception => 'Exception',
            self::Cancelled => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Preparing => 'gray',
            self::Ready => 'blue',
            self::InTransit => 'amber',
            self::Delivered => 'green',
            self::Exception => 'red',
            self::Cancelled => 'gray',
        };
    }

    /** @return array<int,array{value:string,label:string,color:string}> */
    public static function options(): array
    {
        return array_map(
            fn (self $s) => ['value' => $s->value, 'label' => $s->label(), 'color' => $s->color()],
            self::cases(),
        );
    }
}
