<?php

namespace App\Enums;

enum OpportunityStatus: string
{
    case New = 'new';
    case Monitoring = 'monitoring';
    case Qualified = 'qualified';
    case NoBid = 'no_bid';
    case Pursuing = 'pursuing';
    case ProposalInProgress = 'proposal_in_progress';
    case Submitted = 'submitted';
    case UnderEvaluation = 'under_evaluation';
    case Awarded = 'awarded';
    case Lost = 'lost';
    case Cancelled = 'cancelled';
    case Archived = 'archived';

    public function label(): string
    {
        return match($this) {
            self::New => 'New',
            self::Monitoring => 'Monitoring',
            self::Qualified => 'Qualified',
            self::NoBid => 'No Bid',
            self::Pursuing => 'Pursuing',
            self::ProposalInProgress => 'Proposal In Progress',
            self::Submitted => 'Submitted',
            self::UnderEvaluation => 'Under Evaluation',
            self::Awarded => 'Awarded',
            self::Lost => 'Lost',
            self::Cancelled => 'Cancelled',
            self::Archived => 'Archived',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::New => 'blue',
            self::Monitoring => 'yellow',
            self::Qualified => 'indigo',
            self::NoBid => 'red',
            self::Pursuing => 'purple',
            self::ProposalInProgress => 'orange',
            self::Submitted => 'cyan',
            self::UnderEvaluation => 'teal',
            self::Awarded => 'green',
            self::Lost => 'red',
            self::Cancelled => 'gray',
            self::Archived => 'slate',
        };
    }

    public function isActive(): bool
    {
        return in_array($this, [
            self::New, self::Monitoring, self::Qualified,
            self::Pursuing, self::ProposalInProgress,
            self::Submitted, self::UnderEvaluation,
        ]);
    }

    public function isClosed(): bool
    {
        return in_array($this, [self::Awarded, self::Lost, self::Cancelled, self::Archived]);
    }
}
