<?php

namespace App\Modules\Manufacturing\Models;

use App\Models\Concerns\Auditable;
use App\Models\Organization;
use App\Models\User;
use App\Modules\Inventory\Models\Product;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Manufacturing\Database\Factories\WorkOrderFactory;
use App\Modules\Manufacturing\Enums\WorkOrderStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class WorkOrder extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    protected $table = 'manufacturing_work_orders';

    protected $fillable = [
        'ulid', 'organization_id', 'created_by', 'inventory_product_id',
        'manufacturing_bom_id', 'inventory_warehouse_id',
        'number', 'status', 'quantity_planned', 'quantity_produced', 'build_cost',
        'scheduled_date', 'started_at', 'completed_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => WorkOrderStatus::class,
            'quantity_planned' => 'decimal:3',
            'quantity_produced' => 'decimal:3',
            'build_cost' => 'decimal:4',
            'scheduled_date' => 'date',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn ($model) => $model->ulid ??= (string) Str::ulid());
    }

    protected static function newFactory(): WorkOrderFactory
    {
        return WorkOrderFactory::new();
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'inventory_product_id');
    }

    public function bom(): BelongsTo
    {
        return $this->belongsTo(Bom::class, 'manufacturing_bom_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'inventory_warehouse_id');
    }

    public function scopeForOrganization(Builder $query, int $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNotIn('status', [WorkOrderStatus::Completed->value, WorkOrderStatus::Cancelled->value]);
    }
}
