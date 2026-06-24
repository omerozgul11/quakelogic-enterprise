<?php

namespace App\Modules\ExpenseTracker\Models;

use App\Models\Concerns\Auditable;
use App\Models\Organization;
use App\Models\User;
use App\Modules\ExpenseTracker\Database\Factories\ExpenseCategoryFactory;
use App\Modules\ExpenseTracker\Enums\ExpenseStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class ExpenseCategory extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    protected $table = 'expense_categories';

    protected $fillable = [
        'ulid', 'organization_id', 'created_by',
        'name', 'color', 'budget_amount', 'budget_period', 'currency', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'budget_amount' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn ($model) => $model->ulid ??= (string) Str::ulid());
    }

    protected static function newFactory(): ExpenseCategoryFactory
    {
        return ExpenseCategoryFactory::new();
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'expense_category_id');
    }

    public function scopeForOrganization(Builder $query, int $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }

    /** First day of the budget window that $on falls into. */
    public function periodStart(?Carbon $on = null): Carbon
    {
        $on = $on ? $on->copy() : now();

        return match ($this->budget_period) {
            'yearly' => $on->startOfYear(),
            'quarterly' => $on->firstOfQuarter(),
            default => $on->startOfMonth(),
        };
    }

    /** Spend that counts against the budget in the current period (approved+). */
    public function spentThisPeriod(?Carbon $on = null): float
    {
        return (float) $this->expenses()
            ->whereIn('status', $this->spendStatuses())
            ->whereDate('expense_date', '>=', $this->periodStart($on)->toDateString())
            ->sum('amount');
    }

    public function isOverBudget(?Carbon $on = null): bool
    {
        return $this->budget_amount !== null
            && $this->spentThisPeriod($on) > (float) $this->budget_amount;
    }

    /** @return array<int,string> */
    private function spendStatuses(): array
    {
        return [ExpenseStatus::Approved->value, ExpenseStatus::Reimbursed->value, ExpenseStatus::Paid->value];
    }
}
