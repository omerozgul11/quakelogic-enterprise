<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BidprimeImport extends Model
{
    protected $fillable = [
        'organization_id', 'status', 'filters', 'total_fetched',
        'total_created', 'total_updated', 'total_skipped',
        'total_errors', 'started_at', 'completed_at', 'error_message',
    ];

    protected $casts = [
        'filters' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(BidprimeImportItem::class);
    }
}
