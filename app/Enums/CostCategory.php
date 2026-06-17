<?php

namespace App\Enums;

enum CostCategory: string
{
    case Equipment = 'equipment';
    case Shipping = 'shipping';
    case Travel = 'travel';
    case Installation = 'installation';
    case Labor = 'labor';
    case Subcontractor = 'subcontractor';
    case Overhead = 'overhead';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Equipment => 'Equipment / Product',
            self::Shipping => 'Shipping & Import',
            self::Travel => 'Travel',
            self::Installation => 'Installation',
            self::Labor => 'Labor',
            self::Subcontractor => 'Subcontractor',
            self::Overhead => 'Overhead',
            self::Other => 'Other',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Equipment => 'blue',
            self::Shipping => 'cyan',
            self::Travel => 'amber',
            self::Installation => 'violet',
            self::Labor => 'teal',
            self::Subcontractor => 'indigo',
            self::Overhead => 'slate',
            self::Other => 'slate',
        };
    }

    /** @return array<int,array{value:string,label:string}> */
    public static function options(): array
    {
        return array_map(fn (self $c) => ['value' => $c->value, 'label' => $c->label()], self::cases());
    }
}
