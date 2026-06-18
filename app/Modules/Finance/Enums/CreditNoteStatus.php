<?php

namespace App\Modules\Finance\Enums;

enum CreditNoteStatus: string
{
    case Open = 'open';
    case Applied = 'applied';
    case Void = 'void';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Applied => 'Applied',
            self::Void => 'Void',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Open => 'blue',
            self::Applied => 'green',
            self::Void => 'gray',
        };
    }

    /** @return array<int,array{value:string,label:string}> */
    public static function options(): array
    {
        return array_map(fn (self $s) => ['value' => $s->value, 'label' => $s->label()], self::cases());
    }
}
