<?php

namespace App\Modules\Procurement\Models;

use App\Models\Concerns\Auditable;
use App\Models\Organization;
use App\Models\User;
use App\Modules\Procurement\Enums\QuotationStatus;
use App\Modules\Procurement\Models\Concerns\HasProcurementAttachments;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Quotation extends Model
{
    use Auditable, HasProcurementAttachments, SoftDeletes;

    protected $table = 'procurement_quotations';

    protected $fillable = [
        'ulid', 'organization_id', 'created_by', 'procurement_purchase_request_id', 'procurement_supplier_id',
        'number', 'reference_no', 'status', 'quote_date', 'expiry_date', 'currency',
        'subtotal', 'tax_amount', 'discount_total', 'total',
        'vendor_note', 'admin_note', 'terms', 'sent_at', 'accepted_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => QuotationStatus::class,
            'quote_date' => 'date',
            'expiry_date' => 'date',
            'sent_at' => 'datetime',
            'accepted_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'total' => 'decimal:2',
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class, 'procurement_purchase_request_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'procurement_supplier_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuotationItem::class, 'procurement_quotation_id');
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class, 'procurement_quotation_id');
    }

    public function scopeForOrganization(Builder $query, int $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }
}
