<?php

namespace App\Enums;

enum ProposalStatus: string
{
    case InProgress = 'in_progress';
    case Submitted = 'submitted';
    case Pending = 'award_pending';
    case Awarded = 'awarded';
    case Completed = 'completed';
    case Lost = 'lost';
    case Protested = 'protested';

    public function label(): string
    {
        return match($this) {
            self::InProgress => 'In Progress',
            self::Submitted => 'Submitted',
            self::Pending => 'Award Pending',
            self::Awarded => 'Awarded',
            self::Completed => 'Completed',
            self::Lost => 'Lost',
            self::Protested => 'Protested',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::InProgress => 'blue',
            self::Submitted => 'indigo',
            self::Pending => 'orange',
            self::Awarded => 'green',
            self::Completed => 'teal',
            self::Lost => 'red',
            self::Protested => 'purple',
        };
    }

    public function isActive(): bool
    {
        return in_array($this, [self::InProgress, self::Submitted, self::Pending, self::Protested]);
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Awarded, self::Completed, self::Lost]);
    }

    /** Statuses that count as won business (awarded, including finished work). */
    public static function wonValues(): array
    {
        return [self::Awarded->value, self::Completed->value];
    }
}
