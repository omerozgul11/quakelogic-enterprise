<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryMilestone extends Model
{
    use HasFactory;

    protected $fillable = [
        'contract_id', 'title', 'due_date', 'completed_at', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'completed_at' => 'date',
        ];
    }

    public function contract(): BelongsTo { return $this->belongsTo(Contract::class); }
}
