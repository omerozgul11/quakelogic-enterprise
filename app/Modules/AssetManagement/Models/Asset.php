<?php

namespace App\Modules\AssetManagement\Models;

use App\Models\Company;
use App\Models\Concerns\Auditable;
use App\Models\Organization;
use App\Models\User;
use App\Modules\AssetManagement\Database\Factories\AssetFactory;
use App\Modules\AssetManagement\Enums\AssetStatus;
use App\Modules\Inventory\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Asset extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    protected $table = 'asset_assets';

    protected $fillable = [
        'ulid', 'organization_id', 'created_by', 'inventory_product_id', 'company_id', 'assigned_to',
        'asset_tag', 'name', 'serial_number', 'status', 'category', 'location',
        'latitude', 'longitude', 'condition',
        'purchase_cost', 'current_value', 'currency',
        'purchased_at', 'warranty_expires_at', 'deployed_at', 'retired_at', 'notes', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'status' => AssetStatus::class,
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'purchase_cost' => 'decimal:2',
            'current_value' => 'decimal:2',
            'purchased_at' => 'date',
            'warranty_expires_at' => 'date',
            'deployed_at' => 'date',
            'retired_at' => 'date',
            'metadata' => 'array',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn ($model) => $model->ulid ??= (string) Str::ulid());
    }

    protected static function newFactory(): AssetFactory
    {
        return AssetFactory::new();
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

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function maintenanceRecords(): HasMany
    {
        return $this->hasMany(MaintenanceRecord::class, 'asset_id');
    }

    public function scopeForOrganization(Builder $query, int $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }

    public function warrantyActive(): bool
    {
        return $this->warranty_expires_at !== null && $this->warranty_expires_at->isFuture();
    }
}
