<?php

namespace App\Modules\Inventory\Models;

use App\Models\User;
use App\Modules\Inventory\Enums\MovementType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Append-only stock ledger entry. `quantity` is signed (+ in / − out) and
 * `quantity_after` snapshots the (product, warehouse) on-hand immediately after
 * this movement, so the history is auditable without replaying the whole ledger.
 */
class Movement extends Model
{
    protected $table = 'inventory_movements';

    protected $fillable = [
        'ulid', 'organization_id', 'created_by',
        'inventory_product_id', 'inventory_warehouse_id', 'inventory_location_id',
        'type', 'quantity', 'unit_cost', 'quantity_after',
        'reference_type', 'reference_id', 'transfer_group', 'note', 'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => MovementType::class,
            'quantity' => 'decimal:3',
            'unit_cost' => 'decimal:4',
            'quantity_after' => 'decimal:3',
            'occurred_at' => 'datetime',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn ($model) => $model->ulid ??= (string) Str::ulid());
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'inventory_product_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'inventory_warehouse_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'inventory_location_id');
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
