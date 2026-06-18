<?php

namespace App\Modules\ServiceDesk\Enums;

enum TicketType: string
{
    case Support = 'support';
    case Service = 'service';
    case Rma = 'rma';
    case FieldService = 'field_service';

    public function label(): string
    {
        return match ($this) {
            self::Support => 'Support',
            self::Service => 'Service',
            self::Rma => 'RMA',
            self::FieldService => 'Field Service',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Support => 'blue',
            self::Service => 'indigo',
            self::Rma => 'amber',
            self::FieldService => 'green',
        };
    }

    /** @return array<int,array{value:string,label:string}> */
    public static function options(): array
    {
        return array_map(fn (self $t) => ['value' => $t->value, 'label' => $t->label()], self::cases());
    }
}
