<?php

namespace App\Modules\Procurement\Models;

use App\Models\Organization;
use App\Modules\Inventory\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * A single supplier ↔ inventory-product link: the supplier's own part number
 * and price for one of our products. `supplier_price` is their price, i.e. our
 * purchasing cost. Upserted per (product, supplier) pair from a dropped price
 * list.
 */
class SupplierProduct extends Model
{
    use SoftDeletes;

    protected $table = 'procurement_supplier_products';

    protected $fillable = [
        'ulid', 'organization_id', 'inventory_product_id', 'procurement_supplier_id',
        'supplier_sku', 'supplier_price', 'currency', 'lead_time_days', 'last_imported_at', 'source_document',
    ];

    protected function casts(): array
    {
        return [
            'supplier_price' => 'decimal:4',
            'lead_time_days' => 'integer',
            'last_imported_at' => 'datetime',
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

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'procurement_supplier_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'inventory_product_id');
    }
}
