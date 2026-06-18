<?php

namespace App\Modules\Inventory\Enums;

enum ProductType: string
{
    case Good = 'good';
    case FinishedGood = 'finished_good';
    case Component = 'component';
    case RawMaterial = 'raw_material';
    case Kit = 'kit';
    case Service = 'service';

    public function label(): string
    {
        return match ($this) {
            self::Good => 'Good',
            self::FinishedGood => 'Finished Good',
            self::Component => 'Component',
            self::RawMaterial => 'Raw Material',
            self::Kit => 'Kit / Bundle',
            self::Service => 'Service',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Good => 'blue',
            self::FinishedGood => 'green',
            self::Component => 'indigo',
            self::RawMaterial => 'amber',
            self::Kit => 'purple',
            self::Service => 'gray',
        };
    }

    /** Services and (by default) kits are not physically stock-tracked. */
    public function tracksStock(): bool
    {
        return ! in_array($this, [self::Service], true);
    }

    /** @return array<int,array{value:string,label:string}> */
    public static function options(): array
    {
        return array_map(fn (self $t) => ['value' => $t->value, 'label' => $t->label()], self::cases());
    }
}
