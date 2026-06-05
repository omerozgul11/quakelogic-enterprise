<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FollowUpSchedule extends Model
{
    protected $fillable = [
        'organization_id', 'name', 'trigger_event', 'delay_days',
        'follow_up_type', 'subject_template', 'message_template',
        'is_active', 'assign_to_owner', 'assign_to_user_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'assign_to_owner' => 'boolean',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
