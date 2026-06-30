<?php

namespace App\Models\Crm;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * A single tick-off line within a project checklist.
 */
class ProjectChecklistItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'crm_project_checklist_items';

    protected $fillable = [
        'ulid', 'organization_id', 'crm_project_checklist_id', 'done_by',
        'text', 'is_done', 'position', 'done_at',
    ];

    protected function casts(): array
    {
        return [
            'is_done' => 'boolean',
            'position' => 'integer',
            'done_at' => 'datetime',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn ($model) => $model->ulid ??= (string) Str::ulid());
    }

    public function checklist(): BelongsTo
    {
        return $this->belongsTo(ProjectChecklist::class, 'crm_project_checklist_id');
    }

    public function doneBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'done_by');
    }
}
