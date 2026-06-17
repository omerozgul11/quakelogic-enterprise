<?php

namespace App\Models\Crm;

use App\Enums\InvoiceStatus;
use App\Models\Company;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Invoice extends Model
{
    use HasFactory, SoftDeletes;
    use \App\Models\Concerns\Auditable;

    protected $table = 'crm_invoices';

    protected $fillable = [
        'ulid', 'organization_id', 'created_by', 'owner_id', 'company_id', 'crm_project_id',
        'number', 'kind', 'status', 'issue_date', 'due_date',
        'subtotal', 'tax_rate', 'tax_amount', 'discount_amount', 'total', 'amount_paid',
        'currency', 'notes', 'terms',
    ];

    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'issue_date' => 'date',
            'due_date' => 'date',
            'subtotal' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'amount_paid' => 'decimal:2',
        ];
    }

    protected $appends = ['balance'];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn ($model) => $model->ulid ??= (string) Str::ulid());
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'crm_project_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class, 'crm_invoice_id')->orderBy('position')->orderBy('id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'crm_invoice_id');
    }

    public function getBalanceAttribute(): string
    {
        return number_format(max(0, (float) $this->total - (float) $this->amount_paid), 2, '.', '');
    }

    public function isEstimate(): bool
    {
        return $this->kind === 'estimate';
    }

    /**
     * Recompute subtotal/tax/total from the line items. Pure money arithmetic
     * (decimals, never floats for storage). Caller persists.
     */
    public function recalculateTotals(): void
    {
        $subtotal = (float) $this->items()->sum('amount');
        $taxAmount = round($subtotal * ((float) $this->tax_rate / 100), 2);
        $total = round($subtotal + $taxAmount - (float) $this->discount_amount, 2);

        $this->subtotal = $subtotal;
        $this->tax_amount = $taxAmount;
        $this->total = max(0, $total);
    }

    /**
     * Recompute amount_paid from completed payments and move the status to
     * Paid / PartiallyPaid where appropriate. Never overrides Void/Declined.
     */
    public function syncPaymentState(): void
    {
        $paid = (float) $this->payments()->where('status', 'completed')->sum('amount');
        $this->amount_paid = $paid;

        if (in_array($this->status, [InvoiceStatus::Void, InvoiceStatus::Declined], true)) {
            return;
        }

        $total = (float) $this->total;
        if ($total > 0 && $paid >= $total) {
            $this->status = InvoiceStatus::Paid;
        } elseif ($paid > 0) {
            $this->status = InvoiceStatus::PartiallyPaid;
        }
    }

    public function scopeForOrganization(Builder $query, int $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }
}
