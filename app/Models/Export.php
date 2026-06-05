<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Export extends Model
{
    protected $fillable = [
        'organization_id', 'user_id', 'type', 'format', 'status',
        'filters', 'file_path', 'file_size', 'row_count',
        'started_at', 'completed_at', 'expires_at', 'error_message',
    ];

    protected $casts = [
        'filters' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
