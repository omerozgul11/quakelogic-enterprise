<?php

namespace App\Services\Crm;

use App\Enums\ProjectStatus;
use App\Models\Crm\Project;
use App\Models\Crm\ProjectMember;
use App\Models\Crm\ProjectSetting;
use App\Models\Opportunity;
use App\Models\ProposalFile;
use App\Models\ProposalSubmission;
use App\Models\User;
use App\Services\Notifications\Notifier;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Turns an awarded proposal (or opportunity) into a managed project. Idempotent
 * — a given proposal/opportunity yields at most one project, so repeated award
 * transitions never spawn duplicates. Copies the key fields and attachments,
 * assigns ownership from the source, seeds the team, writes the activity feed
 * and notifies the owner + admins.
 */
class ProjectCreationService
{
    public function __construct(
        private ProjectNumberService $numbers,
        private ProjectActivityService $activity,
        private Notifier $notifier,
    ) {}

    /** Fetch (or lazily create) the org's Project Management settings. */
    public function settingsFor(int $organizationId): ProjectSetting
    {
        return ProjectSetting::firstOrCreate(['organization_id' => $organizationId]);
    }

    /**
     * Award entry point used by the proposal workflow. Honours the org's
     * auto_create_on_award setting and never throws — a project-creation failure
     * must never block an award. Returns the project (new or existing) or null.
     */
    public function handleProposalAwarded(ProposalSubmission $proposal, ?User $actor = null): ?Project
    {
        try {
            if (! $this->settingsFor($proposal->organization_id)->auto_create_on_award) {
                return null;
            }

            return $this->createFromProposal($proposal, $actor, automatic: true);
        } catch (\Throwable $e) {
            Log::error('Auto project creation from awarded proposal failed', [
                'proposal_id' => $proposal->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Create a project from a proposal. Idempotent on proposal_submission_id —
     * if a project already exists for this proposal it is returned untouched.
     */
    public function createFromProposal(ProposalSubmission $proposal, ?User $actor = null, bool $automatic = true): Project
    {
        $existing = Project::withTrashed()->where('proposal_submission_id', $proposal->id)->first();
        if ($existing) {
            return $existing;
        }

        $settings = $this->settingsFor($proposal->organization_id);

        $ownerId = $proposal->owner_id ?: $proposal->created_by;
        $managerId = $this->resolveManagerId($settings, $ownerId, $proposal->created_by);
        $budget = ((float) $proposal->award_value) > 0 ? $proposal->award_value : $proposal->proposal_value;

        $project = Project::create([
            'organization_id' => $proposal->organization_id,
            'created_by' => $actor?->id ?? $proposal->created_by,
            'owner_id' => $ownerId,
            'project_manager_id' => $managerId,
            'company_id' => $proposal->company_id,
            'proposal_submission_id' => $proposal->id,
            'opportunity_id' => $proposal->opportunity_id,
            'name' => $proposal->project_name ?: ('Project for '.$proposal->proposal_number),
            'project_number' => $this->numbers->generate($proposal->organization_id, $settings->number_prefix),
            'status' => $this->defaultStatus($settings)->value,
            'description' => $proposal->description ?: $proposal->scope_summary,
            'notes' => $proposal->notes,
            'start_date' => $proposal->pop_start,
            'due_date' => $proposal->pop_end,
            'budget' => $budget,
            'progress' => 0,
            'created_via' => $automatic ? 'automatic' : 'manual',
        ]);

        $this->seedTeam($project, $ownerId, $managerId, $settings, $actor?->id);
        $this->copyProposalFiles($proposal, $project);

        $source = $automatic
            ? "automatically created from awarded proposal {$proposal->proposal_number}"
            : "created from proposal {$proposal->proposal_number}";
        $this->activity->log($project, $actor?->id, 'created', "Project {$source}.", [
            'proposal_number' => $proposal->proposal_number,
            'automatic' => $automatic,
        ]);

        if ($settings->notify_on_create) {
            $this->notifier->projectCreated($project, $actor);
        }

        return $project;
    }

    /**
     * Create a project from an awarded opportunity. Skips creation (returning the
     * existing project, if any) when the opportunity — or one of its proposals —
     * already has a project, so an opportunity award + a proposal award can't
     * both spawn one.
     */
    public function createFromOpportunity(Opportunity $opportunity, ?User $actor = null, bool $automatic = true): ?Project
    {
        $existing = Project::withTrashed()
            ->where(function ($q) use ($opportunity) {
                $q->where('opportunity_id', $opportunity->id)
                    ->orWhereIn('proposal_submission_id', $opportunity->proposals()->pluck('id'));
            })
            ->first();
        if ($existing) {
            return $existing;
        }

        $settings = $this->settingsFor($opportunity->organization_id);

        $ownerId = $opportunity->owner_id ?: ($opportunity->assigned_to ?: $opportunity->created_by);
        $managerId = $this->resolveManagerId($settings, $ownerId, $opportunity->created_by);

        $project = Project::create([
            'organization_id' => $opportunity->organization_id,
            'created_by' => $actor?->id ?? $opportunity->created_by,
            'owner_id' => $ownerId,
            'project_manager_id' => $managerId,
            'company_id' => $opportunity->company_id,
            'opportunity_id' => $opportunity->id,
            'name' => $opportunity->title ?: ('Project for opportunity #'.$opportunity->id),
            'project_number' => $this->numbers->generate($opportunity->organization_id, $settings->number_prefix),
            'status' => $this->defaultStatus($settings)->value,
            'description' => $opportunity->description ?: $opportunity->scope,
            'notes' => $opportunity->notes,
            'start_date' => $opportunity->period_of_performance_start,
            'due_date' => $opportunity->period_of_performance_end,
            'budget' => $opportunity->estimated_value,
            'progress' => 0,
            'created_via' => $automatic ? 'automatic' : 'manual',
        ]);

        $this->seedTeam($project, $ownerId, $managerId, $settings, $actor?->id);

        $source = $automatic ? 'automatically created from awarded opportunity' : 'created from opportunity';
        $this->activity->log($project, $actor?->id, 'created', ucfirst("Project {$source} \"{$opportunity->title}\"."), [
            'opportunity_id' => $opportunity->id,
            'automatic' => $automatic,
        ]);

        if ($settings->notify_on_create) {
            $this->notifier->projectCreated($project, $actor);
        }

        return $project;
    }

    /** Opportunity award entry point — honours settings, never throws. */
    public function handleOpportunityAwarded(Opportunity $opportunity, ?User $actor = null): ?Project
    {
        try {
            if (! $this->settingsFor($opportunity->organization_id)->auto_create_on_award) {
                return null;
            }

            return $this->createFromOpportunity($opportunity, $actor, automatic: true);
        } catch (\Throwable $e) {
            Log::error('Auto project creation from awarded opportunity failed', [
                'opportunity_id' => $opportunity->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function resolveManagerId(ProjectSetting $settings, ?int $ownerId, ?int $creatorId): ?int
    {
        return match ($settings->default_manager_rule) {
            'proposal_creator' => $creatorId,
            'unassigned' => null,
            default => $ownerId,
        };
    }

    private function defaultStatus(ProjectSetting $settings): ProjectStatus
    {
        return ProjectStatus::tryFrom((string) $settings->default_status) ?? ProjectStatus::default();
    }

    /** Add owner (+manager, +any configured default members) to the project team. */
    private function seedTeam(Project $project, ?int $ownerId, ?int $managerId, ProjectSetting $settings, ?int $actorId): void
    {
        if ($ownerId) {
            $this->addMember($project, $ownerId, 'manager', 'Project Owner', $actorId);
        }
        if ($managerId && $managerId !== $ownerId) {
            $this->addMember($project, $managerId, 'manager', 'Project Manager', $actorId);
        }
        foreach ((array) $settings->default_member_ids as $memberId) {
            $memberId = (int) $memberId;
            if ($memberId && $memberId !== $ownerId && $memberId !== $managerId) {
                $this->addMember($project, $memberId, 'member', null, $actorId);
            }
        }
    }

    private function addMember(Project $project, int $userId, string $role, ?string $responsibility, ?int $actorId): void
    {
        ProjectMember::firstOrCreate(
            ['crm_project_id' => $project->id, 'user_id' => $userId],
            [
                'organization_id' => $project->organization_id,
                'added_by' => $actorId,
                'role' => $role,
                'responsibility' => $responsibility,
                'is_active' => true,
            ],
        );
    }

    /** Copy the proposal's current-version files into the project (best-effort). */
    private function copyProposalFiles(ProposalSubmission $proposal, Project $project): void
    {
        try {
            $files = ProposalFile::where('proposal_submission_id', $proposal->id)
                ->where('is_current_version', true)
                ->get();

            foreach ($files as $file) {
                try {
                    if (! Storage::disk($file->disk)->exists($file->path)) {
                        continue;
                    }
                    $ext = pathinfo($file->original_filename, PATHINFO_EXTENSION);
                    $stored = (string) Str::ulid().($ext ? '.'.$ext : '');
                    $newPath = "crm-projects/{$project->id}/{$stored}";
                    Storage::disk($file->disk)->copy($file->path, $newPath);

                    $project->files()->create([
                        'organization_id' => $project->organization_id,
                        'uploaded_by' => $file->uploaded_by,
                        'display_name' => $file->display_name ?: $file->original_filename,
                        'original_filename' => $file->original_filename,
                        'stored_filename' => $stored,
                        'disk' => $file->disk,
                        'path' => $newPath,
                        'mime_type' => $file->mime_type,
                        'size' => $file->size,
                        'checksum' => $file->checksum,
                        'source' => 'proposal',
                    ]);
                } catch (\Throwable $e) {
                    Log::warning('Failed copying a proposal file into a project', [
                        'proposal_file_id' => $file->id,
                        'project_id' => $project->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Failed copying proposal files into a project', [
                'proposal_id' => $proposal->id,
                'project_id' => $project->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
