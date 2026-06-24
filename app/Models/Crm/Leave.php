<?php

namespace App\Models\Crm;

use App\Models\Concerns\Auditable;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A member's time-off over a date range (inclusive). Drives the "On leave"
 * figure on the CRM team-presence strip. A member is "on leave" on a given day
 * when that day falls within [start_date, end_date].
 */
class Leave extends Model
{
    use Auditable, SoftDeletes;

    protected $table = 'crm_leaves';

    protected $fillable = [
        'ulid', 'organization_id', 'user_id', 'created_by',
        'start_date', 'end_date', 'type', 'note',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
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

    /** Leaves that cover the given day (defaults to today). */
    public function scopeCoveringDate(Builder $query, ?string $date = null): Builder
    {
        $date ??= Carbon::now()->toDateString();

        return $query->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date);
    }
}
