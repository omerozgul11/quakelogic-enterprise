<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommissionAdjustment extends Model
{
    protected $fillable = ['commission_id', 'adjusted_by', 'amount', 'reason', 'notes'];
    protected function casts(): array { return ['amount' => 'decimal:2']; }
    public function commission(): BelongsTo { return $this->belongsTo(Commission::class); }
    public function adjustedBy(): BelongsTo { return $this->belongsTo(User::class, 'adjusted_by'); }
}
