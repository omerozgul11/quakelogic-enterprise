<?php

namespace App\Modules\Procurement\Models;

use App\Models\User;
use App\Modules\Procurement\Enums\BillPaymentApprovalStatus;
use App\Modules\Procurement\Models\Concerns\HasApprovals;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class BillPayment extends Model
{
    use HasApprovals;

    protected $table = 'procurement_bill_payments';

    protected $fillable = [
        'ulid', 'organization_id', 'procurement_bill_id',
        'amount', 'payment_method', 'paid_on', 'reference', 'note',
        'approval_status', 'requested_by', 'approved_by', 'recorded_by', 'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'approval_status' => BillPaymentApprovalStatus::class,
            'amount' => 'decimal:2',
            'paid_on' => 'date',
            'approved_at' => 'datetime',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn ($model) => $model->ulid ??= (string) Str::ulid());
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class, 'procurement_bill_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
