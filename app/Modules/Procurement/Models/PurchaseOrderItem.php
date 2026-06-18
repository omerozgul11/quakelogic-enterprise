<?php

namespace App\Modules\Procurement\Models;

use App\Modules\Inventory\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PurchaseOrderItem extends Model
{
    protected $table = 'procurement_purchase_order_items';

    protected $fillable = [
        'ulid', 'organization_id', 'procurement_purchase_order_id', 'inventory_product_id',
        'description', 'sku', 'quantity_ordered', 'quantity_received', 'unit_cost', 'line_total', 'position',
    ];

    protected function casts(): array
    {
        return [
            'quantity_ordered' => 'decimal:3',
            'quantity_received' => 'decimal:3',
            'unit_cost' => 'decimal:4',
            'line_total' => 'decimal:2',
            'position' => 'integer',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn ($model) => $model->ulid ??= (string) Str::ulid());
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'procurement_purchase_order_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'inventory_product_id');
    }

    /** Quantity still awaiting receipt. */
    public function outstanding(): float
    {
        return max(0, (float) $this->quantity_ordered - (float) $this->quantity_received);
    }

    public function isFullyReceived(): bool
    {
        return (float) $this->quantity_received >= (float) $this->quantity_ordered;
    }
}
