<?php

namespace App\Models\Crm;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * One installation site for a project: where the work happens and everything a
 * field engineer needs to get in and work safely (access, security, utilities,
 * hazards, emergency services). A project may have more than one site; one is
 * flagged primary.
 */
class ProjectSite extends Model
{
    use HasFactory, SoftDeletes;
    use \App\Models\Concerns\Auditable;

    protected $table = 'crm_project_sites';

    protected $fillable = [
        'ulid', 'organization_id', 'crm_project_id', 'created_by',
        'name', 'is_primary',
        'address', 'latitude', 'longitude', 'maps_url',
        'access_instructions', 'loading_dock', 'parking', 'working_hours', 'gate_hours',
        'security_requirements', 'badge_required', 'escort_required', 'ppe_required',
        'forklift_available', 'crane_available', 'internet_available', 'power_available',
        'water_available', 'compressed_air_available', 'utilities_notes', 'environmental_conditions',
        'hazards', 'lockout_tagout', 'high_voltage', 'confined_space', 'fall_protection',
        'chemical_hazards', 'emergency_assembly_point', 'nearest_hospital', 'hospital_phone',
        'police_phone', 'fire_phone', 'site_safety_contact', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'badge_required' => 'boolean',
            'escort_required' => 'boolean',
            'forklift_available' => 'boolean',
            'crane_available' => 'boolean',
            'internet_available' => 'boolean',
            'power_available' => 'boolean',
            'water_available' => 'boolean',
            'compressed_air_available' => 'boolean',
            'high_voltage' => 'boolean',
            'confined_space' => 'boolean',
            'fall_protection' => 'boolean',
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

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(ProjectContact::class, 'crm_project_site_id');
    }
}
