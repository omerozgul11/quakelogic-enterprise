<?php

namespace App\Modules\ExpenseTracker\Models;

use App\Models\Company;
use App\Models\Concerns\Auditable;
use App\Models\Crm\Project;
use App\Models\Organization;
use App\Models\ProposalSubmission;
use App\Models\User;
use App\Modules\ExpenseTracker\Database\Factories\ExpenseFactory;
use App\Modules\ExpenseTracker\Enums\ExpenseStatus;
use App\Modules\ExpenseTracker\Enums\PaymentMethod;
use App\Modules\ExpenseTracker\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Expense extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    protected $table = 'expenses';

    protected $fillable = [
        'ulid', 'organization_id', 'created_by', 'owner_id', 'approved_by',
        'expense_category_id', 'recurring_expense_id', 'company_id', 'crm_project_id', 'proposal_id',
        'number', 'vendor', 'description', 'amount', 'currency', 'amount_paid', 'payment_method', 'status',
        'is_billable', 'expense_date', 'due_date', 'submitted_at', 'approved_at', 'reimbursed_at', 'paid_at',
        'reject_reason', 'notes', 'metadata',
        'source', 'quickbooks_id', 'quickbooks_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ExpenseStatus::class,
            'payment_method' => PaymentMethod::class,
            'amount' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'is_billable' => 'boolean',
            'expense_date' => 'date',
            'due_date' => 'date',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'reimbursed_at' => 'datetime',
            'paid_at' => 'datetime',
            'quickbooks_synced_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn ($model) => $model->ulid ??= (string) Str::ulid());
    }

    protected static function newFactory(): ExpenseFactory
    {
        return ExpenseFactory::new();
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

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function recurring(): BelongsTo
    {
        return $this->belongsTo(RecurringExpense::class, 'recurring_expense_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'crm_project_id');
    }

    public function proposal(): BelongsTo
    {
        return $this->belongsTo(ProposalSubmission::class, 'proposal_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ExpenseAttachment::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(ExpensePayment::class)->latest('paid_on');
    }

    /** Outstanding balance = total minus what has been paid (never negative). */
    public function balanceDue(): float
    {
        return max(0.0, (float) $this->amount - (float) $this->amount_paid);
    }

    /** Derived from amount vs amount_paid so it can never drift out of sync. */
    public function paymentStatus(): PaymentStatus
    {
        $paid = (float) $this->amount_paid;
        if ($paid <= 0) {
            return PaymentStatus::Due;
        }

        return $paid + 0.005 >= (float) $this->amount ? PaymentStatus::Paid : PaymentStatus::PartiallyPaid;
    }

    /** True once past its due date with a balance still outstanding. */
    public function isOverdue(): bool
    {
        return $this->due_date !== null
            && $this->balanceDue() > 0
            && $this->due_date->isPast();
    }

    public function scopeForOrganization(Builder $query, int $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }

    public function scopeSpend(Builder $query): Builder
    {
        return $query->whereIn('status', [
            ExpenseStatus::Approved->value,
            ExpenseStatus::Reimbursed->value,
            ExpenseStatus::Paid->value,
        ]);
    }
}
