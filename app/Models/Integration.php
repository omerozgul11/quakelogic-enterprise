<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Integration extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'organization_id', 'name', 'type', 'status',
        'encrypted_credentials', 'settings', 'last_synced_at',
        'sync_error_message', 'sync_count',
    ];

    protected $hidden = ['encrypted_credentials'];

    protected $casts = [
        'settings' => 'array',
        'last_synced_at' => 'datetime',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
