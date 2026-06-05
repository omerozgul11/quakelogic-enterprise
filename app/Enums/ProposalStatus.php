<?php

namespace App\Enums;

enum ProposalStatus: string
{
    case Draft = 'draft';
    case InProgress = 'in_progress';
    case UnderReview = 'under_review';
    case Submitted = 'submitted';
    case Pending = 'pending';
    case ClarificationRequested = 'clarification_requested';
    case Negotiation = 'negotiation';
    case Awarded = 'awarded';
    case Lost = 'lost';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match($this) {
            self::Draft => 'Draft',
            self::InProgress => 'In Progress',
            self::UnderReview => 'Under Review',
            self::Submitted => 'Submitted',
            self::Pending => 'Pending',
            self::ClarificationRequested => 'Clarification Requested',
            self::Negotiation => 'Negotiation',
            self::Awarded => 'Awarded',
            self::Lost => 'Lost',
            self::Cancelled => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Draft => 'gray',
            self::InProgress => 'blue',
            self::UnderReview => 'yellow',
            self::Submitted => 'indigo',
            self::Pending => 'orange',
            self::ClarificationRequested => 'amber',
            self::Negotiation => 'purple',
            self::Awarded => 'green',
            self::Lost => 'red',
            self::Cancelled => 'slate',
        };
    }

    public function isActive(): bool
    {
        return in_array($this, [
            self::Draft, self::InProgress, self::UnderReview,
            self::Submitted, self::Pending, self::ClarificationRequested, self::Negotiation,
        ]);
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Awarded, self::Lost, self::Cancelled]);
    }
}
