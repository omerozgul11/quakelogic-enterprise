<?php

namespace App\Modules\Inventory\Models;

use App\Models\Concerns\Auditable;
use App\Models\Organization;
use App\Models\User;
use App\Modules\Inventory\Database\Factories\WarehouseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Warehouse extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    protected $table = 'inventory_warehouses';

    protected $fillable = [
        'ulid', 'organization_id', 'created_by',
        'code', 'name', 'type',
        'address_line1', 'city', 'state', 'postal_code', 'country',
        'is_default', 'is_active', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn ($model) => $model->ulid ??= (string) Str::ulid());
    }

    protected static function newFactory(): WarehouseFactory
    {
        return WarehouseFactory::new();
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function locations(): HasMany
    {
        return $this->hasMany(Location::class, 'inventory_warehouse_id');
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class, 'inventory_warehouse_id');
    }

    public function scopeForOrganization(Builder $query, int $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
