<?php

namespace App\Services\Opportunities;

use App\Models\Opportunity;
use App\Models\OpportunityEvent;
use App\Models\User;

/**
 * Appends immutable entries to an opportunity's timeline and keeps
 * `last_activity_at` current. Every meaningful action on an opportunity
 * (assignment, claim, reaction, stage/status change, note, email, file upload,
 * review, submission, award, escalation, reassignment) should be recorded here
 * so the executive dashboard always has a complete, never-deleted history.
 */
class OpportunityTimelineService
{
    public const DISCOVERED = 'discovered';
    public const ASSIGNED = 'assigned';
    public const ACCEPTED = 'accepted';
    public const CLAIMED = 'claimed';
    public const REASSIGNED = 'reassigned';
    public const UNLOCKED = 'unlocked';
    public const REACTION = 'reaction';
    public const STAGE_CHANGED = 'stage_changed';
    public const STATUS_CHANGED = 'status_changed';
    public const NOTE = 'note';
    public const EMAIL = 'email';
    public const MEETING = 'meeting';
    public const FILE_UPLOADED = 'file_uploaded';
    public const REVIEW = 'review';
    public const SUBMITTED = 'submitted';
    public const AWARDED = 'awarded';
    public const ESCALATED = 'escalated';

    /**
     * Record one timeline entry. By default this also bumps the opportunity's
     * `last_activity_at`; pass $touchActivity = false for purely historical
     * backfills (e.g. recording the original discovery time).
     *
     * @param  array<string,mixed>  $meta
     */
    public function record(
        Opportunity $opportunity,
        string $type,
        string $description,
        ?User $actor = null,
        array $meta = [],
        bool $touchActivity = true,
        ?\DateTimeInterface $at = null,
    ): OpportunityEvent {
        $event = OpportunityEvent::create([
            'organization_id' => $opportunity->organization_id,
            'opportunity_id' => $opportunity->id,
            'user_id' => $actor?->id,
            'type' => $type,
            'description' => mb_substr($description, 0, 1024),
            'meta' => $meta === [] ? null : $meta,
            'created_at' => $at ?? now(),
        ]);

        if ($touchActivity) {
            // Quietly — an activity bump shouldn't itself spawn an audit/event.
            $opportunity->forceFill(['last_activity_at' => now()])->saveQuietly();
        }

        return $event;
    }
}
