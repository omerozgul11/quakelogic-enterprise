<?php

namespace App\Models;

use App\Enums\ProposalStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Laravel\Scout\Searchable;

class ProposalSubmission extends Model
{
    use HasFactory, Searchable, SoftDeletes;

    protected $fillable = [
        'ulid', 'organization_id', 'opportunity_id', 'created_by', 'updated_by',
        'owner_id', 'proposal_manager_id', 'agency_id', 'company_id',
        'proposal_number', 'solicitation_number', 'project_name', 'status',
        'submission_channel', 'submission_methods', 'submission_confirmation_number',
        'due_date', 'submission_date', 'award_date',
        'proposal_value', 'award_value', 'currency', 'estimated_margin',
        'place_of_performance', 'period_of_performance', 'pop_start', 'pop_end',
        'description', 'scope_summary', 'technical_approach_summary',
        'loss_reason', 'lessons_learned', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => ProposalStatus::class,
            'submission_methods' => 'array',
            'due_date' => 'date',
            'submission_date' => 'date',
            'award_date' => 'date',
            'pop_start' => 'date',
            'pop_end' => 'date',
            'proposal_value' => 'decimal:2',
            'award_value' => 'decimal:2',
            'estimated_margin' => 'decimal:2',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn($model) => $model->ulid ??= (string) Str::ulid());
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function proposalManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proposal_manager_id');
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(ProposalStatusHistory::class);
    }

    public function teamMembers(): HasMany
    {
        return $this->hasMany(ProposalTeamMember::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(ProposalFile::class)->where('is_current_version', true);
    }

    public function allFiles(): HasMany
    {
        return $this->hasMany(ProposalFile::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(ProposalNote::class);
    }

    public function complianceMatrices(): HasMany
    {
        return $this->hasMany(ComplianceMatrix::class);
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(Commission::class);
    }

    public function followUps(): HasMany
    {
        return $this->hasMany(FollowUp::class);
    }

    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'proposal_number' => $this->proposal_number,
            'project_name' => $this->project_name,
            'solicitation_number' => $this->solicitation_number,
            'status' => $this->status?->value,
        ];
    }

    public function scopeForOrganization($query, int $organizationId)
    {
        return $query->where('organization_id', $organizationId);
    }
}
