<?php

namespace App\Modules\Manufacturing\Models;

use App\Modules\Inventory\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class BomItem extends Model
{
    protected $table = 'manufacturing_bom_items';

    protected $fillable = [
        'ulid', 'organization_id', 'manufacturing_bom_id', 'inventory_product_id',
        'quantity_per', 'notes', 'position',
    ];

    protected function casts(): array
    {
        return [
            'quantity_per' => 'decimal:3',
            'position' => 'integer',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn ($model) => $model->ulid ??= (string) Str::ulid());
    }

    public function bom(): BelongsTo
    {
        return $this->belongsTo(Bom::class, 'manufacturing_bom_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'inventory_product_id');
    }
}
