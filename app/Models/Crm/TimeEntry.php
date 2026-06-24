<?php

namespace App\Models\Crm;

use App\Models\Concerns\Auditable;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * A single clock-in / clock-out shift segment. `clock_out` is null while the
 * user is still clocked in. Multiple entries per day are allowed (e.g. a break
 * splits the day into two segments).
 */
class TimeEntry extends Model
{
    use Auditable, SoftDeletes;

    protected $table = 'crm_time_entries';

    protected $fillable = [
        'ulid', 'organization_id', 'user_id', 'created_by',
        'clock_in', 'clock_out', 'note', 'source',
    ];

    protected function casts(): array
    {
        return [
            'clock_in' => 'datetime',
            'clock_out' => 'datetime',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeForOrganization(Builder $query, int $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }

    public function isOpen(): bool
    {
        return $this->clock_out === null;
    }

    /** Worked minutes for a closed entry; null while still open. */
    public function minutes(): ?int
    {
        return $this->clock_out ? $this->clock_in->diffInMinutes($this->clock_out) : null;
    }
}
