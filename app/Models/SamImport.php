<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SamImport extends Model
{
    protected $fillable = [
        'organization_id', 'triggered_by', 'status', 'query_params',
        'total_records', 'imported_records', 'updated_records',
        'skipped_records', 'error_records', 'error_log',
        'started_at', 'completed_at',
    ];

    protected $casts = [
        'query_params' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SamImportItem::class);
    }
}
