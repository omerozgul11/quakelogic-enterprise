<?php

namespace App\Modules\Finance\Models;

use App\Models\Crm\Invoice;
use App\Models\Crm\Payment;
use App\Models\Organization;
use App\Models\User;
use App\Modules\Finance\Enums\PaymentIntentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A gateway checkout attempt against a CRM invoice. On capture it links to the
 * crm_payments row recorded for the collected amount.
 */
class PaymentIntent extends Model
{
    protected $table = 'finance_payment_intents';

    protected $fillable = [
        'ulid', 'organization_id', 'created_by', 'crm_invoice_id', 'crm_payment_id',
        'provider', 'reference', 'checkout_url', 'amount', 'currency', 'status', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => PaymentIntentStatus::class,
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
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

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'crm_invoice_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'crm_payment_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeForOrganization(Builder $query, int $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }
}
