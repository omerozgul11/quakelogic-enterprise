<?php

namespace App\Modules\ExpenseTracker\Enums;

enum ExpenseStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Reimbursed = 'reimbursed';
    case Paid = 'paid';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Submitted',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Reimbursed => 'Reimbursed',
            self::Paid => 'Paid',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Submitted => 'amber',
            self::Approved => 'indigo',
            self::Rejected => 'red',
            self::Reimbursed => 'green',
            self::Paid => 'emerald',
        };
    }

    /** Only draft / rejected expenses are still editable by their owner. */
    public function canEdit(): bool
    {
        return in_array($this, [self::Draft, self::Rejected], true);
    }

    /** Counts toward "spend" on the dashboard / budgets (approved and beyond). */
    public function countsAsSpend(): bool
    {
        return in_array($this, [self::Approved, self::Reimbursed, self::Paid], true);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Reimbursed, self::Paid], true);
    }

    /** @return array<int,array{value:string,label:string}> */
    public static function options(): array
    {
        return array_map(fn (self $s) => ['value' => $s->value, 'label' => $s->label()], self::cases());
    }
}
