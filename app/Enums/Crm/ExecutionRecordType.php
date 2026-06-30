<?php

namespace App\Enums\Crm;

/**
 * The kind of field-execution record kept against a project — the install,
 * commissioning, training, warranty, inspection and service events that make up
 * the on-site lifecycle.
 */
enum ExecutionRecordType: string
{
    case Installation = 'installation';
    case Commissioning = 'commissioning';
    case Training = 'training';
    case Warranty = 'warranty';
    case Inspection = 'inspection';
    case Service = 'service';

    public function label(): string
    {
        return match ($this) {
            self::Installation => 'Installation',
            self::Commissioning => 'Commissioning',
            self::Training => 'Training',
            self::Warranty => 'Warranty',
            self::Inspection => 'Inspection',
            self::Service => 'Service',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Installation => 'blue',
            self::Commissioning => 'indigo',
            self::Training => 'green',
            self::Warranty => 'amber',
            self::Inspection => 'cyan',
            self::Service => 'purple',
        };
    }

    /** @return array<int,array{value:string,label:string,color:string}> */
    public static function options(): array
    {
        return array_map(
            fn (self $t) => ['value' => $t->value, 'label' => $t->label(), 'color' => $t->color()],
            self::cases(),
        );
    }
}
