<?php

namespace App\Models\Crm;

use App\Models\Concerns\Auditable;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * A CRM automation rule: trigger + conditions + actions. Evaluated by
 * {@see \App\Services\Crm\AutomationEngine}. Actions are deliberately limited to
 * safe in-app effects (no auto-sending external email).
 */
class Automation extends Model
{
    use Auditable, SoftDeletes;

    protected $table = 'crm_automations';

    protected $fillable = [
        'ulid', 'organization_id', 'created_by', 'name', 'is_active',
        'trigger_event', 'conditions', 'actions', 'run_count', 'last_run_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'conditions' => 'array',
            'actions' => 'array',
            'last_run_at' => 'datetime',
        ];
    }

    public const TRIGGERS = ['lead.created', 'lead.stage_changed'];

    public const ACTION_TYPES = ['create_followup', 'notify', 'assign_owner', 'log_activity'];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn ($model) => $model->ulid ??= (string) Str::ulid());
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeForOrganization(Builder $query, int $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }
}
