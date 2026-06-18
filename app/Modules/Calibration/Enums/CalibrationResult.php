<?php

namespace App\Modules\Calibration\Enums;

enum CalibrationResult: string
{
    case Pass = 'pass';
    case Limited = 'limited';
    case Fail = 'fail';

    public function label(): string
    {
        return match ($this) {
            self::Pass => 'Pass',
            self::Limited => 'Pass (Limited)',
            self::Fail => 'Fail',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pass => 'green',
            self::Limited => 'amber',
            self::Fail => 'red',
        };
    }

    /** @return array<int,array{value:string,label:string}> */
    public static function options(): array
    {
        return array_map(fn (self $r) => ['value' => $r->value, 'label' => $r->label()], self::cases());
    }
}
