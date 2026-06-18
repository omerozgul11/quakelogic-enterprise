<?php

namespace App\Modules\Procurement\Models;

use App\Models\Company;
use App\Models\Concerns\Auditable;
use App\Models\Organization;
use App\Models\User;
use App\Modules\Procurement\Database\Factories\SupplierFactory;
use App\Modules\Procurement\Enums\SupplierStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Supplier extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    protected $table = 'procurement_suppliers';

    protected $fillable = [
        'ulid', 'organization_id', 'created_by', 'owner_id', 'company_id',
        'code', 'name', 'category', 'status', 'email', 'phone', 'website',
        'address_line1', 'city', 'state', 'postal_code', 'country',
        'payment_terms', 'currency', 'tax_id', 'lead_time_days', 'rating', 'notes', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'status' => SupplierStatus::class,
            'lead_time_days' => 'integer',
            'rating' => 'integer',
            'metadata' => 'array',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn ($model) => $model->ulid ??= (string) Str::ulid());
    }

    protected static function newFactory(): SupplierFactory
    {
        return SupplierFactory::new();
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(SupplierContact::class, 'procurement_supplier_id');
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class, 'procurement_supplier_id');
    }

    public function scopeForOrganization(Builder $query, int $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', SupplierStatus::Active->value);
    }
}
