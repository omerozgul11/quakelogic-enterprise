<?php

namespace App\Enums;

/**
 * The assignment/ownership lifecycle of an opportunity — distinct from the BD
 * pipeline `OpportunityStatus`. This tracks accountability (who owns it and how
 * far the work has progressed), driving aging, escalation and the executive
 * oversight dashboard.
 */
enum OpportunityAssignmentStage: string
{
    case Unassigned = 'unassigned';
    case Assigned = 'assigned';
    case Accepted = 'accepted';
    case InProgress = 'in_progress';
    case ProposalDrafting = 'proposal_drafting';
    case UnderReview = 'under_review';
    case Submitted = 'submitted';
    case Won = 'won';
    case Lost = 'lost';
    case Abandoned = 'abandoned';

    public function label(): string
    {
        return match ($this) {
            self::Unassigned => 'Unassigned',
            self::Assigned => 'Assigned',
            self::Accepted => 'Accepted',
            self::InProgress => 'In Progress',
            self::ProposalDrafting => 'Proposal Drafting',
            self::UnderReview => 'Under Review',
            self::Submitted => 'Submitted',
            self::Won => 'Won',
            self::Lost => 'Lost',
            self::Abandoned => 'Abandoned',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Unassigned => 'gray',
            self::Assigned => 'blue',
            self::Accepted => 'indigo',
            self::InProgress => 'purple',
            self::ProposalDrafting => 'orange',
            self::UnderReview => 'amber',
            self::Submitted => 'cyan',
            self::Won => 'green',
            self::Lost => 'red',
            self::Abandoned => 'slate',
        };
    }

    /** Stages where the opportunity is actively owned and being worked. */
    public function isActive(): bool
    {
        return in_array($this, [
            self::Assigned, self::Accepted, self::InProgress,
            self::ProposalDrafting, self::UnderReview,
        ], true);
    }

    /** Terminal stages — no further accountability tracking needed. */
    public function isClosed(): bool
    {
        return in_array($this, [self::Won, self::Lost, self::Abandoned], true);
    }

    /** Once claimed, the opportunity sits in (or beyond) In Progress. */
    public function isClaimed(): bool
    {
        return ! in_array($this, [self::Unassigned, self::Assigned, self::Accepted], true);
    }

    /**
     * The stages a user can move an owned opportunity through from the UI, in
     * order. Discovery/assignment/claim transitions are driven by their own
     * actions, not this picker.
     */
    public static function workStages(): array
    {
        return [
            self::InProgress, self::ProposalDrafting, self::UnderReview,
            self::Submitted, self::Won, self::Lost, self::Abandoned,
        ];
    }

    /** @return array<int,array{value:string,label:string,color:string}> */
    public static function options(): array
    {
        return array_map(fn (self $c) => [
            'value' => $c->value,
            'label' => $c->label(),
            'color' => $c->color(),
        ], self::cases());
    }
}
