<?php

namespace App\Models\Crm;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * One entry in a CRM record's activity timeline. `subject` is the Lead / Company
 * / Contact it hangs off; `user_id` null means the system or an automation
 * created it. See {@see \App\Services\Crm\ActivityLogger} for the write path.
 */
class Activity extends Model
{
    use SoftDeletes;

    protected $table = 'crm_activities';

    protected $fillable = [
        'ulid', 'organization_id', 'subject_type', 'subject_id',
        'user_id', 'type', 'body', 'meta', 'happened_at',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'happened_at' => 'datetime',
        ];
    }

    /** Types a user can log by hand (system types like stage_change are written by the app). */
    public const MANUAL_TYPES = ['note', 'call', 'email', 'meeting'];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function ($model) {
            $model->ulid ??= (string) Str::ulid();
            $model->happened_at ??= now();
        });
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function scopeForOrganization(Builder $query, int $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }
}
