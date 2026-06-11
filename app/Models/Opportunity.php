<?php

namespace App\Models;

use App\Enums\CaptureStage;
use App\Enums\OpportunitySource;
use App\Enums\OpportunityStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Laravel\Scout\Searchable;

class Opportunity extends Model
{
    use HasFactory, Searchable, SoftDeletes;

    protected $fillable = [
        'ulid', 'organization_id', 'created_by', 'updated_by', 'assigned_to', 'owner_id',
        'title', 'solicitation_number', 'opportunity_number', 'source', 'external_id', 'source_url',
        'status', 'capture_stage', 'set_aside_type', 'contract_type', 'naics_code', 'psc_code',
        'agency_name', 'sub_agency_name', 'agency_id', 'company_id',
        'place_of_performance_city', 'place_of_performance_state', 'place_of_performance_country',
        'estimated_value', 'estimated_value_low', 'estimated_value_high', 'currency', 'probability_of_win',
        'posted_date', 'due_date', 'response_deadline', 'award_date',
        'period_of_performance_start', 'period_of_performance_end',
        'description', 'scope', 'requirements_summary', 'notes', 'go_no_go_notes',
        'go_no_go_decision', 'go_no_go_decided_by', 'go_no_go_decided_at',
        'raw_source_data', 'matched_keywords', 'is_duplicate_flagged', 'duplicate_of', 'canonical_hash',
    ];

    protected function casts(): array
    {
        return [
            'status' => OpportunityStatus::class,
            'source' => OpportunitySource::class,
            'capture_stage' => CaptureStage::class,
            'posted_date' => 'date',
            'due_date' => 'date',
            'response_deadline' => 'date',
            'award_date' => 'date',
            'period_of_performance_start' => 'date',
            'period_of_performance_end' => 'date',
            'go_no_go_decided_at' => 'datetime',
            'estimated_value' => 'decimal:2',
            'estimated_value_low' => 'decimal:2',
            'estimated_value_high' => 'decimal:2',
            'probability_of_win' => 'decimal:2',
            'is_duplicate_flagged' => 'boolean',
            'raw_source_data' => 'array',
            'matched_keywords' => 'array',
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

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function capturePlan(): HasOne
    {
        return $this->hasOne(CapturePlan::class);
    }

    public function proposals(): HasMany
    {
        return $this->hasMany(ProposalSubmission::class);
    }

    public function amendments(): HasMany
    {
        return $this->hasMany(OpportunityAmendment::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(OpportunityAssignment::class);
    }

    public function competitors(): HasMany
    {
        return $this->hasMany(OpportunityCompetitor::class);
    }

    public function partners(): HasMany
    {
        return $this->hasMany(OpportunityPartner::class);
    }

    public function goNoGoReviews(): HasMany
    {
        return $this->hasMany(GoNoGoReview::class);
    }

    public function followUps(): HasMany
    {
        return $this->hasMany(FollowUp::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'taskable_id')->where('taskable_type', self::class);
    }

    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'solicitation_number' => $this->solicitation_number,
            'agency_name' => $this->agency_name,
            'status' => $this->status?->value,
            'source' => $this->source?->value,
            'naics_code' => $this->naics_code,
            'description' => substr((string) $this->description, 0, 500),
        ];
    }

    public function getDueDateRemainingAttribute(): ?int
    {
        return $this->due_date ? now()->diffInDays($this->due_date, false) : null;
    }

    public function scopeActive($query)
    {
        return $query->whereNotIn('status', ['awarded', 'lost', 'cancelled', 'archived']);
    }

    public function scopeForOrganization($query, int $organizationId)
    {
        return $query->where('organization_id', $organizationId);
    }
}
