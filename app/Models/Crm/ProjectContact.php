<?php

namespace App\Models\Crm;

use App\Enums\Crm\ProjectContactCategory;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * A typed stakeholder contact for a project — the customer-side / site people a
 * field engineer needs to reach (procurement, facilities, IT, security,
 * receiving, an emergency contact, …). Optionally tied to a specific site.
 */
class ProjectContact extends Model
{
    use HasFactory, SoftDeletes;
    use \App\Models\Concerns\Auditable;

    protected $table = 'crm_project_contacts';

    protected $fillable = [
        'ulid', 'organization_id', 'crm_project_id', 'crm_project_site_id', 'created_by',
        'category', 'name', 'title', 'company', 'phone', 'mobile', 'email',
        'preferred_contact_method', 'availability', 'is_emergency', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'category' => ProjectContactCategory::class,
            'is_emergency' => 'boolean',
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

    public function site(): BelongsTo
    {
        return $this->belongsTo(ProjectSite::class, 'crm_project_site_id');
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
