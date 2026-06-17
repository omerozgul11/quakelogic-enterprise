<?php

namespace App\Models\Crm;

use App\Enums\ProjectStatus;
use App\Models\Company;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Project extends Model
{
    use HasFactory, SoftDeletes;
    use \App\Models\Concerns\Auditable;

    protected $table = 'crm_projects';

    protected $fillable = [
        'ulid', 'organization_id', 'created_by', 'owner_id', 'company_id',
        'name', 'code', 'status', 'description',
        'start_date', 'due_date', 'completed_at', 'budget', 'progress',
    ];

    protected function casts(): array
    {
        return [
            'status' => ProjectStatus::class,
            'start_date' => 'date',
            'due_date' => 'date',
            'completed_at' => 'datetime',
            'budget' => 'decimal:2',
            'progress' => 'integer',
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

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'crm_project_id')->orderBy('position')->orderBy('id');
    }

    public function scopeForOrganization(Builder $query, int $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }
}
