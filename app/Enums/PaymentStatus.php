<?php

namespace App\Enums;

/** Phase 5 — where a contract sits in the invoice → payment cycle. */
enum PaymentStatus: string
{
    case NotInvoiced = 'not_invoiced';
    case Invoiced = 'invoiced';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case Overdue = 'overdue';

    public function label(): string
    {
        return match ($this) {
            self::NotInvoiced => 'Not Invoiced',
            self::Invoiced => 'Invoiced',
            self::PartiallyPaid => 'Partially Paid',
            self::Paid => 'Paid',
            self::Overdue => 'Overdue',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::NotInvoiced => 'slate',
            self::Invoiced => 'blue',
            self::PartiallyPaid => 'amber',
            self::Paid => 'green',
            self::Overdue => 'red',
        };
    }
}
