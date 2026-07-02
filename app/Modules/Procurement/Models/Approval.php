<?php

namespace App\Modules\Procurement\Models;

use App\Models\Organization;
use App\Models\User;
use App\Modules\Procurement\Enums\ApprovalStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

/**
 * A per-document running approval chain, instantiated from an ApprovalFlow when
 * the document is submitted. Walks its steps in order; the first pending step is
 * the current one, a reject ends the chain, and all-approved completes it.
 */
class Approval extends Model
{
    protected $table = 'procurement_approvals';

    protected $fillable = [
        'ulid', 'organization_id', 'approvable_type', 'approvable_id',
        'procurement_approval_flow_id', 'status', 'submitted_by', 'submitted_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ApprovalStatus::class,
            'submitted_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn ($m) => $m->ulid ??= (string) Str::ulid());
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function approvable(): MorphTo
    {
        return $this->morphTo();
    }

    public function flow(): BelongsTo
    {
        return $this->belongsTo(ApprovalFlow::class, 'procurement_approval_flow_id');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(ApprovalStep::class, 'procurement_approval_id')->orderBy('position')->orderBy('id');
    }

    /** The first step still awaiting a decision, or null if none remain. */
    public function currentStep(): ?ApprovalStep
    {
        return $this->steps->firstWhere('status', ApprovalStatus::Pending);
    }
}
