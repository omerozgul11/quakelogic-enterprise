<?php

namespace App\Models\Crm;

use App\Enums\QuickContactCategory;
use App\Models\Concerns\Auditable;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * A frequently-dialed reference contact (bank desk, carrier line, agency, etc.)
 * shared across the organization. Distinct from {@see \App\Models\Contact},
 * which holds the people at client companies.
 */
class QuickContact extends Model
{
    use Auditable, SoftDeletes;

    protected $table = 'crm_quick_contacts';

    protected $fillable = [
        'ulid', 'organization_id', 'created_by',
        'name', 'organization_name', 'category',
        'phone', 'extension', 'email', 'website', 'notes',
        'is_pinned', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'category' => QuickContactCategory::class,
            'is_pinned' => 'boolean',
            'sort_order' => 'integer',
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeForOrganization(Builder $query, int $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }
}
