<?php

namespace App\Models\Crm;

use App\Enums\ProjectStatus;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Opportunity;
use App\Models\Organization;
use App\Models\ProposalSubmission;
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
        'ulid', 'organization_id', 'created_by', 'owner_id', 'project_manager_id', 'company_id',
        'proposal_submission_id', 'opportunity_id', 'contact_id',
        'name', 'code', 'project_number', 'status', 'description', 'notes',
        'address', 'poc_name', 'poc_role', 'poc_phone', 'poc_email',
        'reference_numbers', 'logistics', 'specs',
        'start_date', 'due_date', 'completed_at', 'budget', 'progress', 'created_via',
        'ai_briefing', 'ai_briefing_generated_at', 'ai_briefing_by',
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
            'ai_briefing_generated_at' => 'datetime',
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

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function projectManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'project_manager_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function proposal(): BelongsTo
    {
        return $this->belongsTo(ProposalSubmission::class, 'proposal_submission_id');
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }

    public function briefingAuthor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ai_briefing_by');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'crm_project_id')->orderBy('position')->orderBy('id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(ProjectMember::class, 'crm_project_id');
    }

    public function activeMembers(): HasMany
    {
        return $this->members()->where('is_active', true);
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(ProjectMilestone::class, 'crm_project_id')->orderBy('sort_order')->orderBy('due_date');
    }

    // Named projectNotes (not notes) to avoid colliding with the `notes` text
    // column on crm_projects — an attribute and relation can't share a name.
    public function projectNotes(): HasMany
    {
        return $this->hasMany(ProjectNote::class, 'crm_project_id')->latest();
    }

    public function files(): HasMany
    {
        return $this->hasMany(ProjectFile::class, 'crm_project_id')->latest();
    }

    /** Document folders (one level) for organising project files. */
    public function folders(): HasMany
    {
        return $this->hasMany(ProjectFolder::class, 'crm_project_id')->orderBy('sort_order')->orderBy('name');
    }

    public function vendors(): HasMany
    {
        return $this->hasMany(ProjectVendor::class, 'crm_project_id')->latest();
    }

    /** Installation sites — primary first, then oldest. */
    public function sites(): HasMany
    {
        return $this->hasMany(ProjectSite::class, 'crm_project_id')->orderByDesc('is_primary')->orderBy('id');
    }

    /** Typed customer/site stakeholder contacts (procurement, facilities, …). */
    public function siteContacts(): HasMany
    {
        return $this->hasMany(ProjectContact::class, 'crm_project_id')->orderBy('name');
    }

    /** Equipment being installed for this project. */
    public function equipment(): HasMany
    {
        return $this->hasMany(ProjectEquipment::class, 'crm_project_id')->orderBy('id');
    }

    /** Shipments bringing equipment to (or from) the site. */
    public function shipments(): HasMany
    {
        return $this->hasMany(ProjectShipment::class, 'crm_project_id')->latest();
    }

    /** On-site execution records (install, commissioning, training, …). */
    public function executionRecords(): HasMany
    {
        return $this->hasMany(ProjectExecutionRecord::class, 'crm_project_id')
            ->orderByRaw('scheduled_date is null, scheduled_date')->orderBy('id');
    }

    /** Reusable tick-off checklists (pre-departure, punch list, …). */
    public function checklists(): HasMany
    {
        return $this->hasMany(ProjectChecklist::class, 'crm_project_id')->orderBy('sort_order')->orderBy('id');
    }

    /** Travel arrangements for the project trip (flights, lodging, …). */
    public function travel(): HasMany
    {
        return $this->hasMany(ProjectTravel::class, 'crm_project_id')
            ->orderByRaw('start_at is null, start_at')->orderBy('id');
    }

    /** Captured digital sign-offs (customer / PM / QA / acceptance …). */
    public function signoffs(): HasMany
    {
        return $this->hasMany(ProjectSignoff::class, 'crm_project_id')->latest('signed_at');
    }

    /**
     * Purchase orders raised against this project. Reuses the Procurement
     * module's PO records (cross-module link via crm_project_id).
     */
    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(\App\Modules\Procurement\Models\PurchaseOrder::class, 'crm_project_id')->latest();
    }

    public function activities(): HasMany
    {
        return $this->hasMany(ProjectActivity::class, 'crm_project_id')->latest();
    }

    public function scopeForOrganization(Builder $query, int $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }

    /**
     * Limit the query to projects the given user may see when they lack the
     * `manage all projects` capability: ones they own, manage, are an active
     * team member of, or are assigned a task on.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $q) use ($user) {
            $q->where('owner_id', $user->id)
                ->orWhere('project_manager_id', $user->id)
                ->orWhereHas('members', fn (Builder $m) => $m->where('user_id', $user->id)->where('is_active', true))
                ->orWhereHas('tasks', fn (Builder $t) => $t->where('assigned_to', $user->id));
        });
    }

    /** Is this user the owner, the project manager, or an active team member / task assignee? */
    public function isAssigned(User $user): bool
    {
        if ($this->owner_id === $user->id || $this->project_manager_id === $user->id) {
            return true;
        }

        return $this->members()->where('user_id', $user->id)->where('is_active', true)->exists()
            || $this->tasks()->where('assigned_to', $user->id)->exists();
    }

    /** Is this user the owner or the project manager (a "lead")? */
    public function isLead(User $user): bool
    {
        return $this->owner_id === $user->id || $this->project_manager_id === $user->id;
    }
}
