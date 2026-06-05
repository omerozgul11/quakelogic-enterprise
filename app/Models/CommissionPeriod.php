<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommissionPeriod extends Model
{
    protected $fillable = [
        'organization_id', 'period_month', 'status',
        'total_commission_amount', 'total_users',
        'approved_by', 'approved_at', 'notes',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'total_commission_amount' => 'decimal:2',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
