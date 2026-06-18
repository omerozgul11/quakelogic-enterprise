<?php

namespace App\Modules\Manufacturing\Models;

use App\Models\Concerns\Auditable;
use App\Models\Organization;
use App\Models\User;
use App\Modules\Inventory\Models\Product;
use App\Modules\Manufacturing\Database\Factories\BomFactory;
use App\Modules\Manufacturing\Enums\BomStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Bom extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    protected $table = 'manufacturing_boms';

    protected $fillable = [
        'ulid', 'organization_id', 'created_by', 'inventory_product_id',
        'name', 'version', 'status', 'output_quantity', 'is_default', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => BomStatus::class,
            'output_quantity' => 'decimal:3',
            'is_default' => 'boolean',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn ($model) => $model->ulid ??= (string) Str::ulid());
    }

    protected static function newFactory(): BomFactory
    {
        return BomFactory::new();
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

    public function items(): HasMany
    {
        return $this->hasMany(BomItem::class, 'manufacturing_bom_id');
    }

    public function scopeForOrganization(Builder $query, int $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }
}
