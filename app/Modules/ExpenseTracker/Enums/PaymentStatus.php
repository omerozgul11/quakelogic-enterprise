<?php

namespace App\Modules\ExpenseTracker\Enums;

/**
 * Where an expense/invoice stands on being paid. Derived from amount vs
 * amount_paid (see Expense::paymentStatus()), never stored directly, so it can
 * never drift out of sync with the recorded payments.
 */
enum PaymentStatus: string
{
    case Due = 'due';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';

    public function label(): string
    {
        return match ($this) {
            self::Due => 'Due',
            self::PartiallyPaid => 'Partially paid',
            self::Paid => 'Paid',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Due => 'amber',
            self::PartiallyPaid => 'indigo',
            self::Paid => 'emerald',
        };
    }

    /** @return array<int,array{value:string,label:string}> */
    public static function options(): array
    {
        return array_map(fn (self $s) => ['value' => $s->value, 'label' => $s->label()], self::cases());
    }
}
