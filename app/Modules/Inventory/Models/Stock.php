<?php

namespace App\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * On-hand balance for one (product, warehouse) pair. Mutated only through
 * InventoryService inside a locked transaction — never updated ad hoc.
 */
class Stock extends Model
{
    protected $table = 'inventory_stocks';

    protected $fillable = [
        'organization_id', 'inventory_product_id', 'inventory_warehouse_id',
        'quantity_on_hand', 'quantity_reserved', 'average_cost',
    ];

    protected function casts(): array
    {
        return [
            'quantity_on_hand' => 'decimal:3',
            'quantity_reserved' => 'decimal:3',
            'average_cost' => 'decimal:4',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'inventory_product_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'inventory_warehouse_id');
    }

    /** Free-to-promise quantity (on-hand minus reserved). */
    public function available(): float
    {
        return (float) $this->quantity_on_hand - (float) $this->quantity_reserved;
    }

    public function scopeForOrganization(Builder $query, int $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }
}
