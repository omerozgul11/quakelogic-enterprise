<?php

namespace App\Models;

use App\Enums\CommissionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommissionRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id', 'user_id', 'name', 'type', 'rate', 'fixed_amount',
        'base_on', 'tier_config', 'effective_from', 'effective_to', 'is_active', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'type' => CommissionType::class,
            'rate' => 'decimal:4',
            'fixed_amount' => 'decimal:2',
            'tier_config' => 'array',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(Commission::class);
    }
}
