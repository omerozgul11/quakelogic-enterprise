<?php

namespace App\Modules\Procurement\Models;

use App\Models\Concerns\Auditable;
use App\Models\Crm\Project;
use App\Models\Organization;
use App\Models\User;
use App\Modules\Procurement\Enums\PurchaseRequestStatus;
use App\Modules\Procurement\Models\Concerns\HasApprovals;
use App\Modules\Procurement\Models\Concerns\HasProcurementAttachments;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class PurchaseRequest extends Model
{
    use Auditable, HasApprovals, HasProcurementAttachments, SoftDeletes;

    protected $table = 'procurement_purchase_requests';

    protected $fillable = [
        'ulid', 'organization_id', 'created_by', 'requester_id', 'crm_project_id',
        'number', 'title', 'description', 'department', 'status', 'currency',
        'subtotal', 'tax_amount', 'total',
        'approved_by', 'approved_at', 'rejected_reason', 'notes', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'status' => PurchaseRequestStatus::class,
            'approved_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'metadata' => 'array',
        ];
    }

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

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'crm_project_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseRequestItem::class, 'procurement_purchase_request_id');
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class, 'procurement_purchase_request_id');
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class, 'procurement_purchase_request_id');
    }

    public function scopeForOrganization(Builder $query, int $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }
}
