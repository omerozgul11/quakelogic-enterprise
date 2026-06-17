<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Accepted = 'accepted';     // estimates
    case Declined = 'declined';     // estimates
    case Paid = 'paid';
    case PartiallyPaid = 'partially_paid';
    case Overdue = 'overdue';
    case Void = 'void';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Sent => 'Sent',
            self::Accepted => 'Accepted',
            self::Declined => 'Declined',
            self::Paid => 'Paid',
            self::PartiallyPaid => 'Partially Paid',
            self::Overdue => 'Overdue',
            self::Void => 'Void',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Sent => 'blue',
            self::Accepted => 'indigo',
            self::Declined => 'red',
            self::Paid => 'green',
            self::PartiallyPaid => 'amber',
            self::Overdue => 'red',
            self::Void => 'gray',
        };
    }

    /** Statuses considered settled (no further payment expected). */
    public function isSettled(): bool
    {
        return in_array($this, [self::Paid, self::Void, self::Declined], true);
    }
}
