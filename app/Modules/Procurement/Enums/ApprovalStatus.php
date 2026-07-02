<?php

namespace App\Modules\Procurement\Enums;

/**
 * State of an approval (whole chain) or a single step: pending → approved, or
 * rejected at any point.
 */
enum ApprovalStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'amber',
            self::Approved => 'green',
            self::Rejected => 'red',
        };
    }

    public function isOpen(): bool
    {
        return $this === self::Pending;
    }
}
