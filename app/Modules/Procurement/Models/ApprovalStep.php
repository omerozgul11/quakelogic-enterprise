<?php

namespace App\Modules\Procurement\Models;

use App\Models\User;
use App\Modules\Procurement\Enums\ApprovalStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * One instantiated step of a running Approval: a snapshot of the flow step plus
 * its decision (who, when, note) and any captured digital signature.
 */
class ApprovalStep extends Model
{
    protected $table = 'procurement_approval_steps';

    protected $fillable = [
        'ulid', 'organization_id', 'procurement_approval_id',
        'position', 'name', 'approver_type', 'approver_user_id', 'approver_role', 'require_signature',
        'status', 'decided_by', 'decided_at', 'note', 'signature_path',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'require_signature' => 'boolean',
            'status' => ApprovalStatus::class,
            'decided_at' => 'datetime',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn ($m) => $m->ulid ??= (string) Str::ulid());
    }

    public function approval(): BelongsTo
    {
        return $this->belongsTo(Approval::class, 'procurement_approval_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_user_id');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /** Whether the given user is eligible to decide this step. */
    public function isEligible(User $user): bool
    {
        if ($this->approver_type === 'user') {
            return $this->approver_user_id === $user->id;
        }

        return $this->approver_role !== null && $user->hasRole($this->approver_role);
    }
}
