<?php

namespace App\Models\Crm;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * One piece of equipment for a project install — identity (model/serial/firmware),
 * rigging data (weight/centre of gravity/lift points), placement, and calibration
 * / warranty status. May reference the shipment that carried it and, optionally,
 * a tracked Asset Management record (soft link via asset_id).
 */
class ProjectEquipment extends Model
{
    use HasFactory, SoftDeletes;
    use \App\Models\Concerns\Auditable;

    protected $table = 'crm_project_equipment';

    protected $fillable = [
        'ulid', 'organization_id', 'crm_project_id', 'crm_project_shipment_id', 'asset_id', 'created_by',
        'name', 'product', 'model', 'revision', 'serial_number', 'firmware', 'software_version', 'asset_tag', 'quantity',
        'power', 'voltage', 'weight', 'dimensions', 'center_of_gravity', 'lift_points',
        'rigging_instructions', 'installation_location',
        'calibration_status', 'calibration_due', 'warranty_status', 'warranty_expires', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'calibration_due' => 'date',
            'warranty_expires' => 'date',
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

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(ProjectShipment::class, 'crm_project_shipment_id');
    }

    /** Optional link to a tracked Asset Management asset. */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\AssetManagement\Models\Asset::class, 'asset_id');
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
