<?php

namespace App\Models;

use App\Enums\CostCategory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ProposalCost extends Model
{
    use HasFactory, SoftDeletes;
    use \App\Models\Concerns\Auditable;

    protected $fillable = [
        'ulid', 'organization_id', 'proposal_submission_id', 'created_by',
        'description', 'category', 'amount', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'category' => CostCategory::class,
            'amount' => 'decimal:2',
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

    public function proposal(): BelongsTo
    {
        return $this->belongsTo(ProposalSubmission::class, 'proposal_submission_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeForOrganization($query, int $organizationId)
    {
        return $query->where('organization_id', $organizationId);
    }
}
