<?php

namespace App\Models\Crm;

use App\Enums\Crm\MilestoneStatus;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ProjectMilestone extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'crm_project_milestones';

    protected $fillable = [
        'ulid', 'organization_id', 'crm_project_id', 'created_by',
        'title', 'description', 'due_date', 'completed_at', 'status', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'status' => MilestoneStatus::class,
            'due_date' => 'date',
            'completed_at' => 'datetime',
            'sort_order' => 'integer',
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
