<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * A DHL tracking push subscription this org created — by tracking number or by
 * account. Lifecycle: pending (created) → validating (validate event received,
 * webhook confirmed) → ready (DHL is pushing). Lets the Carriers page show the
 * live connection and attributes inbound pushes to the right org.
 */
class DhlPushSubscription extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_PENDING = 'pending';
    public const STATUS_VALIDATING = 'validating';
    public const STATUS_READY = 'ready';
    public const STATUS_FAILED = 'failed';
    public const STATUS_REMOVED = 'removed';

    public const TYPE_SHIPMENT = 'shipment';
    public const TYPE_ACCOUNT = 'account';

    protected $fillable = [
        'ulid', 'organization_id', 'subscription_id', 'type', 'tracking_number',
        'account_number', 'status', 'secret', 'callback_url', 'last_event_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'last_event_at' => 'datetime',
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

    public function scopeForOrganization(Builder $query, int $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }
}
