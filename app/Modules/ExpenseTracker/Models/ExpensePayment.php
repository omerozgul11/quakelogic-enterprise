<?php

namespace App\Modules\ExpenseTracker\Models;

use App\Models\Organization;
use App\Models\User;
use App\Modules\ExpenseTracker\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ExpensePayment extends Model
{
    use SoftDeletes;

    protected $table = 'expense_payments';

    protected $fillable = [
        'ulid', 'organization_id', 'expense_id', 'created_by',
        'amount', 'currency', 'paid_on', 'method', 'reference', 'note',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_on' => 'date',
            'method' => PaymentMethod::class,
        ];
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn ($model) => $model->ulid ??= (string) Str::ulid());
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
