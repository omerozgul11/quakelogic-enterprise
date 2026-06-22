<?php

namespace App\Models\Crm;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-organization Project Management settings (one row per org). Governs the
 * award→project automation, default status/manager rules, project numbering and
 * notification behaviour.
 */
class ProjectSetting extends Model
{
    use HasFactory;

    protected $table = 'crm_project_settings';

    /**
     * In-memory defaults so a freshly firstOrCreate()'d settings row carries the
     * same values as the DB column defaults (Eloquent doesn't hydrate DB defaults
     * on insert) — otherwise number_prefix/default_status read back as null.
     */
    protected $attributes = [
        'auto_create_on_award' => true,
        'default_status' => 'new',
        'default_manager_rule' => 'proposal_owner',
        'number_prefix' => 'QL-PROJ',
        'notify_on_create' => true,
    ];

    protected $fillable = [
        'organization_id', 'auto_create_on_award', 'default_status',
        'default_manager_rule', 'number_prefix', 'notify_on_create', 'default_member_ids',
    ];

    protected function casts(): array
    {
        return [
            'auto_create_on_award' => 'boolean',
            'notify_on_create' => 'boolean',
            'default_member_ids' => 'array',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
