<?php

namespace App\Modules\Procurement\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * One step of an ApprovalFlow template: who approves (a specific user, or anyone
 * holding a role) and whether a digital signature is required.
 */
class ApprovalFlowStep extends Model
{
    protected $table = 'procurement_approval_flow_steps';

    protected $fillable = [
        'ulid', 'organization_id', 'procurement_approval_flow_id',
        'position', 'name', 'approver_type', 'approver_user_id', 'approver_role', 'require_signature',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'require_signature' => 'boolean',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn ($m) => $m->ulid ??= (string) Str::ulid());
    }

    public function flow(): BelongsTo
    {
        return $this->belongsTo(ApprovalFlow::class, 'procurement_approval_flow_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_user_id');
    }
}
