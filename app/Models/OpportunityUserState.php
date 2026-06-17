<?php

namespace App\Models;

use App\Enums\OpportunityReaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-user state for an opportunity: their digest reaction and the AI match
 * score / recommendation. One row per (opportunity, user).
 */
class OpportunityUserState extends Model
{
    protected $fillable = [
        'organization_id', 'opportunity_id', 'user_id', 'reaction',
        'match_score', 'match_reasons', 'is_recommended', 'recommended_role', 'reacted_at',
    ];

    protected function casts(): array
    {
        return [
            'reaction' => OpportunityReaction::class,
            'match_score' => 'decimal:2',
            'match_reasons' => 'array',
            'is_recommended' => 'boolean',
            'reacted_at' => 'datetime',
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
