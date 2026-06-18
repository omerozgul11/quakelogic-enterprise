<?php

namespace App\Modules\Inventory\Models;

use App\Models\Concerns\Auditable;
use App\Models\Organization;
use App\Models\User;
use App\Modules\Inventory\Database\Factories\ProductFactory;
use App\Modules\Inventory\Enums\ProductType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Product extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    protected $table = 'inventory_products';

    protected $fillable = [
        'ulid', 'organization_id', 'created_by', 'owner_id',
        'sku', 'name', 'type', 'category', 'description', 'unit_of_measure',
        'barcode', 'manufacturer', 'mpn',
        'unit_cost', 'unit_price', 'currency',
        'reorder_point', 'reorder_quantity', 'lead_time_days', 'weight',
        'is_serialized', 'track_inventory', 'is_active', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'type' => ProductType::class,
            'unit_cost' => 'decimal:4',
            'unit_price' => 'decimal:4',
            'reorder_point' => 'decimal:3',
            'reorder_quantity' => 'decimal:3',
            'weight' => 'decimal:3',
            'lead_time_days' => 'integer',
            'is_serialized' => 'boolean',
            'track_inventory' => 'boolean',
            'is_active' => 'boolean',
            'metadata' => 'array',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn ($model) => $model->ulid ??= (string) Str::ulid());
    }

    protected static function newFactory(): ProductFactory
    {
        return ProductFactory::new();
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class, 'inventory_product_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(Movement::class, 'inventory_product_id');
    }

    public function scopeForOrganization(Builder $query, int $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Total on-hand across every warehouse. */
    public function totalOnHand(): float
    {
        return (float) $this->stocks->sum('quantity_on_hand');
    }

    /** Inventory valuation = on-hand × weighted-average cost, summed per warehouse. */
    public function stockValue(): float
    {
        return (float) $this->stocks->sum(fn (Stock $s) => (float) $s->quantity_on_hand * (float) $s->average_cost);
    }

    /** Below reorder point (only meaningful when a reorder point is set). */
    public function isLowStock(): bool
    {
        return $this->reorder_point !== null && $this->totalOnHand() <= (float) $this->reorder_point;
    }
}
