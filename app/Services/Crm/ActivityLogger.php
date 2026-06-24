<?php

namespace App\Services\Crm;

use App\Enums\LeadStatus;
use App\Models\Crm\Activity;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * The single write path for the CRM activity timeline. Keeps logging consistent
 * (org scoping, last_activity_at bump) and out of the controllers. Subjects must
 * expose `organization_id`; if they also have a `last_activity_at` column it is
 * refreshed so list views can sort by recency.
 */
class ActivityLogger
{
    /**
     * @param  array<string,mixed>  $meta
     */
    public function log(Model $subject, string $type, ?string $body = null, ?User $user = null, array $meta = []): Activity
    {
        $activity = new Activity([
            'organization_id' => $subject->getAttribute('organization_id'),
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'user_id' => $user?->id,
            'type' => $type,
            'body' => $body,
            'meta' => $meta ?: null,
        ]);
        $activity->save();

        // Keep the record's recency marker fresh without firing model events.
        if ($subject->isFillable('last_activity_at') || in_array('last_activity_at', $subject->getFillable(), true)) {
            $subject->forceFill(['last_activity_at' => now()])->saveQuietly();
        }

        return $activity;
    }

    public function created(Model $subject, ?User $user, ?string $label = null): Activity
    {
        return $this->log($subject, 'created', $label ?? 'Created', $user);
    }

    public function stageChanged(Model $subject, LeadStatus $from, LeadStatus $to, ?User $user): Activity
    {
        return $this->log(
            $subject,
            'stage_change',
            "Stage moved from {$from->label()} to {$to->label()}",
            $user,
            ['from' => $from->value, 'to' => $to->value],
        );
    }

    public function converted(Model $subject, ?User $user, ?string $label = null): Activity
    {
        return $this->log($subject, 'converted', $label ?? 'Converted to client', $user);
    }

    /** A system/automation note (no human actor). */
    public function system(Model $subject, string $body, array $meta = []): Activity
    {
        return $this->log($subject, 'system', $body, null, $meta);
    }
}
