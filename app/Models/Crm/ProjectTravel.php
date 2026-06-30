<?php

namespace App\Models\Crm;

use App\Enums\Crm\TravelType;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * A single travel arrangement on a project trip — a flight, hotel stay, car
 * rental, ground leg, per-diem allotment or incidental cost.
 */
class ProjectTravel extends Model
{
    use HasFactory, SoftDeletes;
    use \App\Models\Concerns\Auditable;

    protected $table = 'crm_project_travel';

    protected $fillable = [
        'ulid', 'organization_id', 'crm_project_id', 'traveler_id', 'created_by',
        'traveler_name', 'type', 'title', 'status', 'provider', 'confirmation_number',
        'start_at', 'end_at', 'from_location', 'to_location', 'cost', 'currency', 'booking_url', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'type' => TravelType::class,
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'cost' => 'decimal:2',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn ($model) => $model->ulid ??= (string) Str::ulid());
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'crm_project_id');
    }

    public function traveler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'traveler_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
