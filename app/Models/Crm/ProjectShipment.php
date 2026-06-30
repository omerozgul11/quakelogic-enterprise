<?php

namespace App\Models\Crm;

use App\Enums\Carrier;
use App\Enums\Crm\ProjectShipmentStatus;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * A shipment moving equipment to (or from) a project's install site — carrier,
 * tracking, crate/pallet, weights, shock/tilt indicators and handling notes.
 */
class ProjectShipment extends Model
{
    use HasFactory, SoftDeletes;
    use \App\Models\Concerns\Auditable;

    protected $table = 'crm_project_shipments';

    protected $fillable = [
        'ulid', 'organization_id', 'crm_project_id', 'created_by',
        'direction', 'carrier', 'service', 'tracking_number', 'status',
        'shipped_date', 'expected_arrival', 'arrived_date',
        'crate_number', 'package_count', 'pallet_info',
        'weight', 'gross_weight', 'net_weight', 'shipping_weight', 'dimensions',
        'bill_of_lading', 'packing_list', 'forklift_instructions', 'lift_points',
        'shock_indicator', 'tilt_indicator', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'carrier' => Carrier::class,
            'status' => ProjectShipmentStatus::class,
            'shipped_date' => 'date',
            'expected_arrival' => 'date',
            'arrived_date' => 'date',
            'package_count' => 'integer',
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

    public function equipment(): HasMany
    {
        return $this->hasMany(ProjectEquipment::class, 'crm_project_shipment_id');
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
