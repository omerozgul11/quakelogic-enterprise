<?php

namespace App\Enums\Crm;

/**
 * The kind of field-service vendor attached to a project — forklift/trucking
 * companies and the rest of the delivery & installation supply chain.
 */
enum ProjectVendorCategory: string
{
    case Trucking = 'trucking';
    case Forklift = 'forklift';
    case Freight = 'freight';
    case Crane = 'crane';
    case Rigging = 'rigging';
    case Installation = 'installation';
    case Warehouse = 'warehouse';
    case Customs = 'customs';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Trucking => 'Trucking',
            self::Forklift => 'Forklift / Material Handling',
            self::Freight => 'Freight / Carrier',
            self::Crane => 'Crane',
            self::Rigging => 'Rigging',
            self::Installation => 'Installation',
            self::Warehouse => 'Warehouse / Storage',
            self::Customs => 'Customs / Broker',
            self::Other => 'Other',
        };
    }

    /** Pill-safe colour. */
    public function color(): string
    {
        return match ($this) {
            self::Trucking => 'blue',
            self::Forklift => 'amber',
            self::Freight => 'indigo',
            self::Crane => 'purple',
            self::Rigging => 'pink',
            self::Installation => 'green',
            self::Warehouse => 'cyan',
            self::Customs => 'orange',
            self::Other => 'gray',
        };
    }

    /** @return array<int,array{value:string,label:string,color:string}> */
    public static function options(): array
    {
        return array_map(
            fn (self $c) => ['value' => $c->value, 'label' => $c->label(), 'color' => $c->color()],
            self::cases(),
        );
    }
}
