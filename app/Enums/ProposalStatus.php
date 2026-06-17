<?php

namespace App\Enums;

enum ProposalStatus: string
{
    case InProgress = 'in_progress';
    case Submitted = 'submitted';
    case Pending = 'award_pending';
    case ClarificationRequested = 'clarification_requested';
    case Awarded = 'awarded';
    case Completed = 'completed';
    case Lost = 'lost';
    case Protested = 'protested';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match($this) {
            self::InProgress => 'In Progress',
            self::Submitted => 'Submitted',
            self::Pending => 'Award Pending',
            self::ClarificationRequested => 'Clarification Requested',
            self::Awarded => 'Awarded',
            self::Completed => 'Completed',
            self::Lost => 'Lost',
            self::Protested => 'Protested',
            self::Cancelled => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::InProgress => 'blue',
            self::Submitted => 'indigo',
            self::Pending => 'orange',
            self::ClarificationRequested => 'amber',
            self::Awarded => 'green',
            self::Completed => 'teal',
            self::Lost => 'red',
            self::Protested => 'purple',
            self::Cancelled => 'slate',
        };
    }

    public function isActive(): bool
    {
        return in_array($this, [
            self::InProgress,
            self::Submitted, self::Pending, self::ClarificationRequested,
            self::Protested,
        ]);
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Awarded, self::Completed, self::Lost, self::Cancelled]);
    }

    /** Statuses that count as won business (awarded, including finished work). */
    public static function wonValues(): array
    {
        return [self::Awarded->value, self::Completed->value];
    }
}
