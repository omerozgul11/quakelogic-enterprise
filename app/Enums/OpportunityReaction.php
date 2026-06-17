<?php

namespace App\Enums;

/**
 * A user's reaction to an opportunity recommended in their daily digest. Drives
 * the per-user pipeline ("Interested", "In Progress", …) and feeds the executive
 * accountability view (who has / hasn't acted on what they were shown).
 */
enum OpportunityReaction: string
{
    case SaveForLater = 'save_for_later';
    case Interested = 'interested';
    case InProgress = 'in_progress';
    case NotInterested = 'not_interested';
    case AlreadySubmitted = 'already_submitted';
    case NeedsReview = 'needs_review';

    public function label(): string
    {
        return match ($this) {
            self::SaveForLater => 'Save for Later',
            self::Interested => 'Interested',
            self::InProgress => 'In Progress',
            self::NotInterested => 'Not Interested',
            self::AlreadySubmitted => 'Already Submitted',
            self::NeedsReview => 'Needs Review',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::SaveForLater => 'slate',
            self::Interested => 'blue',
            self::InProgress => 'purple',
            self::NotInterested => 'gray',
            self::AlreadySubmitted => 'green',
            self::NeedsReview => 'amber',
        };
    }

    /** Reactions that signal the user is taking the opportunity forward. */
    public function isPositive(): bool
    {
        return in_array($this, [self::Interested, self::InProgress, self::AlreadySubmitted], true);
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
