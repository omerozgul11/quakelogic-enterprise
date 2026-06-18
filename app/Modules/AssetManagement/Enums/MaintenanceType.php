<?php

namespace App\Modules\AssetManagement\Enums;

enum MaintenanceType: string
{
    case Preventive = 'preventive';
    case Corrective = 'corrective';
    case Inspection = 'inspection';
    case Repair = 'repair';
    case Calibration = 'calibration';

    public function label(): string
    {
        return match ($this) {
            self::Preventive => 'Preventive',
            self::Corrective => 'Corrective',
            self::Inspection => 'Inspection',
            self::Repair => 'Repair',
            self::Calibration => 'Calibration',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Preventive => 'blue',
            self::Corrective => 'amber',
            self::Inspection => 'indigo',
            self::Repair => 'red',
            self::Calibration => 'green',
        };
    }

    /** @return array<int,array{value:string,label:string}> */
    public static function options(): array
    {
        return array_map(fn (self $t) => ['value' => $t->value, 'label' => $t->label()], self::cases());
    }
}
