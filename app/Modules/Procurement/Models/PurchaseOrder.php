<?php

namespace App\Modules\Procurement\Models;

use App\Models\Concerns\Auditable;
use App\Models\Organization;
use App\Models\User;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Procurement\Database\Factories\PurchaseOrderFactory;
use App\Modules\Procurement\Enums\PurchaseOrderStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class PurchaseOrder extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    protected $table = 'procurement_purchase_orders';

    protected $fillable = [
        'ulid', 'organization_id', 'crm_project_id', 'created_by', 'procurement_supplier_id', 'inventory_warehouse_id',
        'number', 'status', 'order_date', 'expected_date', 'currency',
        'subtotal', 'tax_rate', 'tax_amount', 'shipping_amount', 'total',
        'notes', 'approved_by', 'approved_at', 'emailed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => PurchaseOrderStatus::class,
            'order_date' => 'date',
            'expected_date' => 'date',
            'approved_at' => 'datetime',
            'emailed_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'shipping_amount' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn ($model) => $model->ulid ??= (string) Str::ulid());
    }

    protected static function newFactory(): PurchaseOrderFactory
    {
        return PurchaseOrderFactory::new();
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'procurement_supplier_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Crm\Project::class, 'crm_project_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'inventory_warehouse_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class, 'procurement_purchase_order_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function scopeForOrganization(Builder $query, int $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNotIn('status', [
            PurchaseOrderStatus::Received->value,
            PurchaseOrderStatus::Closed->value,
            PurchaseOrderStatus::Cancelled->value,
        ]);
    }
}
