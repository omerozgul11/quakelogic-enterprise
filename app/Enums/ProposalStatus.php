<?php

namespace App\Enums;

enum ProposalStatus: string
{
    case InProgress = 'in_progress';
    case Submitted = 'submitted';
    case Pending = 'pending';
    case ClarificationRequested = 'clarification_requested';
    case Awarded = 'awarded';
    case Completed = 'completed';
    case Lost = 'lost';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match($this) {
            self::InProgress => 'In Progress',
            self::Submitted => 'Submitted',
            self::Pending => 'Pending',
            self::ClarificationRequested => 'Clarification Requested',
            self::Awarded => 'Awarded',
            self::Completed => 'Completed',
            self::Lost => 'Lost',
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
            self::Cancelled => 'slate',
        };
    }

    public function isActive(): bool
    {
        return in_array($this, [
            self::InProgress,
            self::Submitted, self::Pending, self::ClarificationRequested,
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
