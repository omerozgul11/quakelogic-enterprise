<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailAccount extends Model
{
    protected $fillable = [
        'user_id', 'provider', 'email',
        'access_token', 'refresh_token', 'token_expires_at', 'scopes', 'connected_at',
        'smtp_host', 'smtp_port', 'smtp_encryption', 'smtp_username', 'smtp_password', 'from_name',
    ];

    protected function casts(): array
    {
        return [
            // OAuth tokens and the SMTP password are encrypted at rest.
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'smtp_password' => 'encrypted',
            'smtp_port' => 'integer',
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
        if ($this->connected_at === null) {
            return false;
        }
        // SMTP: usable once we have a host and a password.
        if ($this->provider === 'smtp') {
            return !empty($this->smtp_host) && !empty($this->smtp_password);
        }
        // OAuth providers: usable once we have an access token.
        return !empty($this->access_token);
    }
}
