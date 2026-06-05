<?php

namespace App\Enums;

enum CommissionType: string
{
    case FixedAmount = 'fixed_amount';
    case Percentage = 'percentage';
    case Tiered = 'tiered';

    public function label(): string
    {
        return match($this) {
            self::FixedAmount => 'Fixed Amount',
            self::Percentage => 'Percentage',
            self::Tiered => 'Tiered',
        };
    }
}
