<?php

namespace App\Enums\Crm;

/**
 * Priority for a CRM project task. Replaces the old free-form low|medium|high|
 * urgent string ('urgent' migrated to 'critical').
 */
enum TaskPriority: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Critical = 'critical';

    public function label(): string
    {
        return match ($this) {
            self::Low => 'Low',
            self::Medium => 'Medium',
            self::High => 'High',
            self::Critical => 'Critical',
        };
    }

    /** Pill-safe colour. */
    public function color(): string
    {
        return match ($this) {
            self::Low => 'gray',
            self::Medium => 'blue',
            self::High => 'amber',
            self::Critical => 'red',
        };
    }

    /** Relative weight for sorting (higher = more urgent). */
    public function weight(): int
    {
        return match ($this) {
            self::Low => 1,
            self::Medium => 2,
            self::High => 3,
            self::Critical => 4,
        };
    }
}
