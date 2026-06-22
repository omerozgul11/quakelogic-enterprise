<?php

namespace App\Models\Crm;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ProjectMember extends Model
{
    use HasFactory;

    protected $table = 'crm_project_members';

    protected $fillable = [
        'ulid', 'organization_id', 'crm_project_id', 'user_id', 'added_by',
        'role', 'responsibility', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
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

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
