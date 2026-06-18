<?php

namespace App\Modules\Manufacturing\Enums;

enum BomStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Active => 'Active',
            self::Archived => 'Archived',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'amber',
            self::Active => 'green',
            self::Archived => 'gray',
        };
    }

    /** Only active BOMs can back a work order. */
    public function isUsable(): bool
    {
        return $this === self::Active;
    }

    /** @return array<int,array{value:string,label:string}> */
    public static function options(): array
    {
        return array_map(fn (self $s) => ['value' => $s->value, 'label' => $s->label()], self::cases());
    }
}
