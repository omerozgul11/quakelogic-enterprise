<?php

namespace App\Models\Crm;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A single human-readable entry in a project's activity feed (the per-project
 * audit trail shown on the detail page). user_id null = system/automation.
 */
class ProjectActivity extends Model
{
    use HasFactory;

    protected $table = 'crm_project_activities';

    protected $fillable = [
        'ulid', 'organization_id', 'crm_project_id', 'user_id',
        'action', 'description', 'meta',
    ];

    protected function casts(): array
    {
        return ['meta' => 'array'];
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
