<?php

namespace App\Modules\Procurement\Models;

use App\Models\Concerns\Auditable;
use App\Models\Organization;
use App\Models\User;
use App\Modules\Procurement\Enums\BillPaymentStatus;
use App\Modules\Procurement\Models\Concerns\HasProcurementAttachments;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Bill extends Model
{
    use Auditable, HasProcurementAttachments, SoftDeletes;

    protected $table = 'procurement_bills';

    protected $fillable = [
        'ulid', 'organization_id', 'created_by', 'procurement_supplier_id', 'procurement_purchase_order_id',
        'number', 'vendor_invoice_number', 'bill_date', 'due_date', 'currency',
        'subtotal', 'tax_rate', 'tax_amount', 'shipping_amount', 'discount_total', 'total', 'amount_paid',
        'payment_status', 'recurring', 'recurring_frequency', 'recurring_cycles', 'recurring_total_cycles',
        'next_recurring_date', 'recurring_parent_id', 'notes', 'terms',
    ];

    protected function casts(): array
    {
        return [
            'payment_status' => BillPaymentStatus::class,
            'bill_date' => 'date',
            'due_date' => 'date',
            'next_recurring_date' => 'date',
            'recurring' => 'boolean',
            'recurring_cycles' => 'integer',
            'recurring_total_cycles' => 'integer',
            'subtotal' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'shipping_amount' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'total' => 'decimal:2',
            'amount_paid' => 'decimal:2',
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

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'procurement_supplier_id');
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'procurement_purchase_order_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(BillItem::class, 'procurement_bill_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(BillPayment::class, 'procurement_bill_id');
    }

    public function recurringParent(): BelongsTo
    {
        return $this->belongsTo(Bill::class, 'recurring_parent_id');
    }

    /** Outstanding balance still owed on this bill. */
    public function balanceDue(): float
    {
        return round((float) $this->total - (float) $this->amount_paid, 2);
    }

    public function scopeForOrganization(Builder $query, int $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }
}
