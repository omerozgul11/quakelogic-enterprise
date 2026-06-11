<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailAccount extends Model
{
    protected $fillable = [
        'user_id', 'provider', 'email',
        'access_token', 'refresh_token', 'token_expires_at', 'scopes', 'connected_at',
    ];

    protected function casts(): array
    {
        return [
            // OAuth tokens are encrypted at rest.
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'token_expires_at' => 'datetime',
            'connected_at' => 'datetime',
            'scopes' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isConnected(): bool
    {
        return $this->connected_at !== null && !empty($this->access_token);
    }
}
