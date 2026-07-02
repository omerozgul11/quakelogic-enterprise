<?php

namespace App\Modules\Procurement\Models;

use App\Modules\Inventory\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PurchaseRequestItem extends Model
{
    protected $table = 'procurement_purchase_request_items';

    protected $fillable = [
        'ulid', 'organization_id', 'procurement_purchase_request_id', 'inventory_product_id',
        'description', 'sku', 'unit', 'quantity', 'unit_cost', 'tax_rate', 'line_total', 'position',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_cost' => 'decimal:4',
            'tax_rate' => 'decimal:2',
            'line_total' => 'decimal:2',
            'position' => 'integer',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn ($model) => $model->ulid ??= (string) Str::ulid());
    }

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class, 'procurement_purchase_request_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'inventory_product_id');
    }
}
