<?php

namespace App\Models\Crm;

use App\Models\Concerns\Auditable;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A dated, assignable CRM follow-up. `subject` (optional) is the Lead / Company /
 * Contact it concerns. Drives the dashboard Today/Overdue/Upcoming queue and the
 * daily reminder. Separate from project tasks ({@see Task}) and proposal
 * follow-ups ({@see \App\Models\FollowUp}).
 */
class FollowUp extends Model
{
    use Auditable, SoftDeletes;

    protected $table = 'crm_follow_ups';

    protected $fillable = [
        'ulid', 'organization_id', 'created_by', 'assigned_to',
        'subject_type', 'subject_id', 'title', 'notes',
        'due_date', 'priority', 'status', 'completed_at', 'completed_by', 'reminded_on',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'completed_at' => 'datetime',
            'reminded_on' => 'date',
        ];
    }

    public const PRIORITIES = ['low', 'normal', 'high'];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn ($model) => $model->ulid ??= (string) Str::ulid());
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function scopeForOrganization(Builder $query, int $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', 'open');
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->where('status', 'open')->whereDate('due_date', '<', Carbon::now()->toDateString());
    }

    public function isOverdue(): bool
    {
        return $this->status === 'open' && $this->due_date?->isBefore(Carbon::today());
    }
}
