<?php

namespace App\Models;

use App\Enums\TemplateCategory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ProposalTemplate extends Model
{
    use HasFactory, SoftDeletes;
    use \App\Models\Concerns\Auditable;

    protected $fillable = [
        'ulid', 'organization_id', 'created_by', 'category', 'title', 'content', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'category' => TemplateCategory::class,
            'is_active' => 'boolean',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn ($model) => $model->ulid ??= (string) Str::ulid());
    }

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }

    public function scopeForOrganization($query, int $organizationId)
    {
        return $query->where('organization_id', $organizationId);
    }
}
