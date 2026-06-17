<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One immutable entry in an opportunity's timeline. Append-only: never updated
 * or deleted, so the complete history (discovery → award) is always preserved.
 * Use OpportunityTimelineService::record() to write these.
 */
class OpportunityEvent extends Model
{
    /** Append-only: created_at is set on insert (DB useCurrent); no updated_at. */
    public $timestamps = false;

    protected $fillable = [
        'organization_id', 'opportunity_id', 'user_id', 'type', 'description', 'meta', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForOrganization($query, int $organizationId)
    {
        return $query->where('organization_id', $organizationId);
    }
}
