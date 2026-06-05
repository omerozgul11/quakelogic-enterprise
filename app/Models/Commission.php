<?php

namespace App\Models;

use App\Enums\CommissionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Commission extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'ulid', 'organization_id', 'user_id', 'proposal_submission_id', 'commission_rule_id',
        'calculated_by', 'approved_by', 'type', 'base_amount', 'rate', 'commission_amount',
        'status', 'period_month', 'notes', 'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => CommissionType::class,
            'base_amount' => 'decimal:2',
            'rate' => 'decimal:4',
            'commission_amount' => 'decimal:2',
            'approved_at' => 'datetime',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn($model) => $model->ulid ??= (string) Str::ulid());
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function proposal(): BelongsTo
    {
        return $this->belongsTo(ProposalSubmission::class, 'proposal_submission_id');
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(CommissionRule::class, 'commission_rule_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(CommissionAdjustment::class);
    }

    public function getTotalAmountAttribute(): float
    {
        $adjustments = $this->adjustments->sum('amount');
        return (float) $this->commission_amount + $adjustments;
    }
}
