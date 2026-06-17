<?php

namespace App\Enums;

enum ShipmentScope: string
{
    case Domestic = 'domestic';
    case International = 'international';

    public function label(): string
    {
        return match ($this) {
            self::Domestic => 'Domestic',
            self::International => 'International',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Domestic => 'blue',
            self::International => 'indigo',
        };
    }
}
