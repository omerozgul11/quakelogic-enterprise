<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * An admin-editable group of keywords (optionally with NAICS codes) used to
 * score opportunities for QuakeLogic relevance. An exclusion group marks a
 * matching opportunity Not Relevant.
 */
class OpportunityKeywordGroup extends Model
{
    use HasFactory, SoftDeletes, Auditable;

    protected $fillable = [
        'ulid', 'organization_id', 'created_by',
        'name', 'keywords', 'naics_codes', 'weight', 'is_exclusion', 'is_active', 'color', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'keywords' => 'array',
            'naics_codes' => 'array',
            'weight' => 'integer',
            'is_exclusion' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn ($model) => $model->ulid ??= (string) Str::ulid());
    }

    public function scopeForOrganization(Builder $query, int $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
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
