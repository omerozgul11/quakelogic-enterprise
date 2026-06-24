<?php

namespace App\Modules\ExpenseTracker\Models;

use App\Models\Company;
use App\Models\Concerns\Auditable;
use App\Models\Crm\Project;
use App\Models\Organization;
use App\Models\ProposalSubmission;
use App\Models\User;
use App\Modules\ExpenseTracker\Database\Factories\RecurringExpenseFactory;
use App\Modules\ExpenseTracker\Enums\PaymentMethod;
use App\Modules\ExpenseTracker\Enums\RecurringFrequency;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class RecurringExpense extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    protected $table = 'recurring_expenses';

    protected $fillable = [
        'ulid', 'organization_id', 'created_by', 'owner_id', 'expense_category_id',
        'company_id', 'crm_project_id', 'proposal_id',
        'name', 'vendor', 'amount', 'currency', 'payment_method', 'is_billable',
        'frequency', 'interval_count', 'start_date', 'end_date', 'next_run_date',
        'last_generated_at', 'auto_approve', 'is_active', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'frequency' => RecurringFrequency::class,
            'payment_method' => PaymentMethod::class,
            'amount' => 'decimal:2',
            'is_billable' => 'boolean',
            'interval_count' => 'integer',
            'start_date' => 'date',
            'end_date' => 'date',
            'next_run_date' => 'date',
            'last_generated_at' => 'datetime',
            'auto_approve' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn ($model) => $model->ulid ??= (string) Str::ulid());
    }

    protected static function newFactory(): RecurringExpenseFactory
    {
        return RecurringExpenseFactory::new();
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

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
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

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'recurring_expense_id');
    }

    public function scopeForOrganization(Builder $query, int $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Is this schedule due to generate an expense as of today? */
    public function isDue(): bool
    {
        if (! $this->is_active) {
            return false;
        }
        $today = now()->startOfDay();
        if ($this->next_run_date->gt($today)) {
            return false;
        }

        return $this->end_date === null || $this->next_run_date->lte($this->end_date);
    }
}
