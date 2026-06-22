<?php

namespace App\Enums\Crm;

enum MilestoneStatus: string
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::InProgress => 'In Progress',
            self::Completed => 'Completed',
        };
    }

    /** Pill-safe colour. */
    public function color(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::InProgress => 'indigo',
            self::Completed => 'green',
        };
    }

    public function isComplete(): bool
    {
        return $this === self::Completed;
    }
}
