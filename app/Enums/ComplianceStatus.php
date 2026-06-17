<?php

namespace App\Enums;

/** Phase 7 — lifecycle of a single compliance item. */
enum ComplianceStatus: string
{
    case Active = 'active';
    case Pending = 'pending';
    case Expired = 'expired';
    case NotApplicable = 'not_applicable';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Pending => 'Pending',
            self::Expired => 'Expired',
            self::NotApplicable => 'N/A',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'green',
            self::Pending => 'amber',
            self::Expired => 'red',
            self::NotApplicable => 'slate',
        };
    }
}
