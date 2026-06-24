<?php

namespace App\Modules\ExpenseTracker\Models;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class QuickBooksConnection extends Model
{
    protected $table = 'quickbooks_connections';

    protected $fillable = [
        'ulid', 'organization_id', 'connected_by', 'realm_id', 'environment',
        'access_token', 'refresh_token', 'token_expires_at', 'refresh_token_expires_at',
        'is_demo', 'push_enabled', 'push_account_id', 'push_expense_account_id',
        'last_synced_at', 'last_sync_status', 'last_sync_message',
    ];

    protected $hidden = ['access_token', 'refresh_token'];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'token_expires_at' => 'datetime',
            'refresh_token_expires_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'is_demo' => 'boolean',
            'push_enabled' => 'boolean',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn ($model) => $model->ulid ??= (string) Str::ulid());
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function connectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'connected_by');
    }

    public function scopeForOrganization(Builder $query, int $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }

    /** Access token expired (or about to, within a 60s skew)? */
    public function tokenExpired(): bool
    {
        return $this->token_expires_at === null || $this->token_expires_at->subSeconds(60)->isPast();
    }
}
