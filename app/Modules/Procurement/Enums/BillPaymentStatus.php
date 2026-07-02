<?php

namespace App\Modules\Procurement\Enums;

/** Whether a bill has been paid, derived from the approved payments against it. */
enum BillPaymentStatus: string
{
    case Unpaid = 'unpaid';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';

    public function label(): string
    {
        return match ($this) {
            self::Unpaid => 'Unpaid',
            self::PartiallyPaid => 'Partially Paid',
            self::Paid => 'Paid',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Unpaid => 'red',
            self::PartiallyPaid => 'amber',
            self::Paid => 'green',
        };
    }

    /** @return array<int,array{value:string,label:string,color:string}> */
    public static function options(): array
    {
        return array_map(fn (self $s) => [
            'value' => $s->value,
            'label' => $s->label(),
            'color' => $s->color(),
        ], self::cases());
    }
}
