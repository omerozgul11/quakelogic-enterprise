<?php

namespace App\Http\Controllers\Web\Crm;

use App\Enums\Crm\MilestoneStatus;
use App\Enums\Crm\TaskPriority;
use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Crm\Invoice;
use App\Models\Crm\Project;
use App\Models\Crm\ProjectMember;
use App\Models\Crm\ProjectMilestone;
use App\Models\Crm\ProjectNote;
use App\Models\Crm\Task;
use App\Models\Crm\TaskComment;
use App\Models\ProposalSubmission;
use App\Models\User;
use App\Services\Crm\ProjectActivityService;
use App\Services\Crm\ProjectCreationService;
use App\Services\Crm\ProjectNumberService;
use App\Services\Notifications\Notifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function __construct(
        private ProjectActivityService $activity,
        private ProjectCreationService $creation,
        private ProjectNumberService $numbers,
        private Notifier $notifier,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Project::class);
        $user = $request->user();
        $manageAll = $user->can('manage all projects');

        $base = Project::query()->where('organization_id', $user->organization_id);
        if (! $manageAll) {
            $base->visibleTo($user);
        }

        $projects = (clone $base)
            ->with(['company:id,name', 'owner:id,name', 'projectManager:id,name'])
            ->withCount([
                'tasks',
                'tasks as completed_tasks_count' => fn ($q) => $q->where('status', TaskStatus::Completed->value),
                'activeMembers as members_count',
            ])
            ->when($request->search, fn ($q, $s) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$s}%")
                ->orWhere('project_number', 'like', "%{$s}%")
                ->orWhere('code', 'like', "%{$s}%")))
            ->when($request->status, fn ($q, $st) => $q->where('status', $st))
            ->when($request->input('owner'), fn ($q, $o) => $q->where('owner_id', $o))
            ->when($request->boolean('mine'), fn ($q) => $q->where(fn ($w) => $w
                ->where('owner_id', $user->id)->orWhere('project_manager_id', $user->id)))
            ->orderByDesc('updated_at')
            ->paginate(15)->withQueryString()
            ->through(fn (Project $p) => $this->rowPayload($p));

        $stats = [
            'total' => (clone $base)->count(),
            'open' => (clone $base)->whereNotIn('status', [ProjectStatus::Completed->value, ProjectStatus::Cancelled->value])->count(),
            'completed' => (clone $base)->where('status', ProjectStatus::Completed->value)->count(),
            'overdue' => (clone $base)
                ->whereNotIn('status', [ProjectStatus::Completed->value, ProjectStatus::Cancelled->value])
                ->whereNotNull('due_date')->whereDate('due_date', '<', now())->count(),
        ];

        return Inertia::render('Crm/Projects/Index', [
            'projects' => $projects,
            'filters' => $request->only(['search', 'status', 'owner', 'mine']),
            'stats' => $stats,
            'companies' => $this->companies($user->organization_id),
            'owners' => $this->orgUsers($user->organization_id),
            'statuses' => $this->statusOptions(),
            'awardableProposals' => $manageAll || $user->can('manage projects')
                ? $this->awardableProposals($user->organization_id) : [],
            'can' => [
                'manage' => $user->can('manage projects'),
                'manageAll' => $manageAll,
                'settings' => $user->can('manage all projects'),
            ],
        ]);
    }

    public function show(Request $request, Project $project): Response
    {
        $this->authorize('view', $project);
        $user = $request->user();

        $project->load([
            'company:id,name', 'owner:id,name', 'projectManager:id,name', 'contact:id,first_name,last_name,email',
            'proposal:id,proposal_number,project_name,status', 'opportunity:id,title,status',
            'tasks.assignee:id,name', 'tasks.comments.author:id,name',
            'members.user:id,name', 'members.addedBy:id,name',
            'milestones', 'projectNotes.author:id,name', 'files.uploader:id,name',
            'activities.user:id,name',
        ]);

        $invoices = Invoice::where('crm_project_id', $project->id)
            ->orderByDesc('issue_date')->get();

        $canManageTasks = $user->can('manageTasks', $project);
        $canManageTeam = $user->can('manageTeam', $project);
        $canAdminister = $user->can('administer', $project);

        return Inertia::render('Crm/Projects/Show', [
            'project' => $this->detailPayload($project),
            'tasks' => $project->tasks->map(fn (Task $t) => $this->taskPayload($t, $user, $canManageTasks))->values(),
            'members' => $project->members->map(fn (ProjectMember $m) => [
                'id' => $m->id,
                'user_id' => $m->user_id,
                'name' => $m->user?->name,
                'role' => $m->role,
                'responsibility' => $m->responsibility,
                'is_active' => $m->is_active,
                'added_by' => $m->addedBy?->name,
                'added_at' => $m->created_at?->toDateString(),
            ])->values(),
            'milestones' => $project->milestones->map(fn (ProjectMilestone $m) => [
                'id' => $m->id,
                'title' => $m->title,
                'description' => $m->description,
                'due_date' => $m->due_date?->toDateString(),
                'completed_at' => $m->completed_at?->toDateString(),
                'status' => $m->status->value,
                'status_label' => $m->status->label(),
                'status_color' => $m->status->color(),
                'sort_order' => $m->sort_order,
            ])->values(),
            'notes' => $project->projectNotes->map(fn (ProjectNote $n) => [
                'id' => $n->id,
                'body' => $n->body,
                'author' => $n->author?->name,
                'author_id' => $n->user_id,
                'created_at' => $n->created_at?->toIso8601String(),
            ])->values(),
            'files' => $project->files->map(fn ($f) => [
                'id' => $f->id,
                'name' => $f->display_name,
                'size' => $f->size,
                'mime_type' => $f->mime_type,
                'source' => $f->source,
                'uploaded_by' => $f->uploader?->name,
                'created_at' => $f->created_at?->toIso8601String(),
            ])->values(),
            'activities' => $project->activities->take(100)->map(fn ($a) => [
                'id' => $a->id,
                'action' => $a->action,
                'description' => $a->description,
                'user' => $a->user?->name,
                'created_at' => $a->created_at?->toIso8601String(),
            ])->values(),
            'invoices' => $invoices->map(fn (Invoice $i) => [
                'id' => $i->id,
                'number' => $i->number,
                'kind' => $i->kind,
                'status' => $i->status instanceof \BackedEnum ? $i->status->value : $i->status,
                'total' => (float) $i->total,
                'amount_paid' => (float) $i->amount_paid,
                'balance' => (float) ($i->total - $i->amount_paid),
            ])->values(),
            'financials' => $this->financials($project, $invoices),
            'companies' => $this->companies($user->organization_id),
            'owners' => $this->orgUsers($user->organization_id),
            'statuses' => $this->statusOptions(),
            'taskStatuses' => collect(TaskStatus::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label(), 'color' => $s->color()]),
            'priorities' => collect(TaskPriority::cases())->map(fn ($p) => ['value' => $p->value, 'label' => $p->label(), 'color' => $p->color()]),
            'milestoneStatuses' => collect(MilestoneStatus::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label(), 'color' => $s->color()]),
            'memberRoles' => ['manager', 'lead', 'engineer', 'member', 'stakeholder', 'viewer'],
            'can' => [
                'manage' => $canManageTasks,
                'manageTeam' => $canManageTeam,
                'administer' => $canAdminister,
                'delete' => $user->can('delete', $project),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Project::class);
        $user = $request->user();

        // Manual creation from an awarded proposal routes through the same
        // idempotent service the award automation uses.
        if ($request->filled('proposal_submission_id')) {
            $proposal = ProposalSubmission::where('organization_id', $user->organization_id)
                ->findOrFail($request->input('proposal_submission_id'));
            $project = $this->creation->createFromProposal($proposal, $user, automatic: false);

            return redirect()->route('crm.projects.show', $project)->with('success', 'Project created from proposal.');
        }

        $data = $this->validateProject($request);
        $admin = $user->can('manage all projects');

        $ownerId = $admin ? ($request->input('owner_id') ?: $user->id) : $user->id;
        $managerId = $request->filled('project_manager_id')
            ? $this->validateOrgUser($request, 'project_manager_id')
            : $ownerId;

        $project = Project::create([
            ...$data,
            'organization_id' => $user->organization_id,
            'created_by' => $user->id,
            'owner_id' => $ownerId,
            'project_manager_id' => $managerId,
            'project_number' => $this->numbers->generate($user->organization_id, $this->creation->settingsFor($user->organization_id)->number_prefix),
            'status' => $data['status'] ?? ProjectStatus::default()->value,
            'created_via' => 'manual',
        ]);

        if ($ownerId) {
            ProjectMember::firstOrCreate(
                ['crm_project_id' => $project->id, 'user_id' => $ownerId],
                ['organization_id' => $project->organization_id, 'added_by' => $user->id, 'role' => 'manager', 'responsibility' => 'Project Owner', 'is_active' => true],
            );
        }

        $this->activity->log($project, $user->id, 'created', 'Project created manually.');
        $this->notifier->projectCreated($project, $user);

        return redirect()->route('crm.projects.show', $project)->with('success', 'Project created.');
    }

    public function update(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);
        $user = $request->user();

        $data = $this->validateProject($request);
        $previousStatus = $project->status;

        // Owner / project-manager reassignment is admin-only.
        if ($user->can('administer', $project)) {
            if ($request->filled('owner_id')) {
                $data['owner_id'] = $this->validateOrgUser($request, 'owner_id');
            }
            if ($request->has('project_manager_id')) {
                $data['project_manager_id'] = $request->filled('project_manager_id')
                    ? $this->validateOrgUser($request, 'project_manager_id') : null;
            }
        }

        $newStatusValue = $data['status'] ?? $previousStatus->value;
        if ($newStatusValue === ProjectStatus::Completed->value && ! $project->completed_at) {
            $data['completed_at'] = now();
        } elseif ($newStatusValue !== ProjectStatus::Completed->value) {
            $data['completed_at'] = null;
        }

        $managerChanged = array_key_exists('project_manager_id', $data) && $data['project_manager_id'] !== $project->project_manager_id;
        $newManagerId = $data['project_manager_id'] ?? null;

        $project->update($data);

        // Side effects: activity + notifications.
        $newStatus = $project->status;
        if ($newStatus !== $previousStatus) {
            $this->activity->log($project, $user->id, 'status_changed', "Status changed from {$previousStatus->label()} to {$newStatus->label()}.");
            $this->notifier->projectStatusChanged($project, $previousStatus, $newStatus, $user);
            if ($newStatus === ProjectStatus::Completed) {
                $this->notifier->projectCompleted($project, $user);
            }
        }
        if ($managerChanged && $newManagerId) {
            $manager = User::find($newManagerId);
            if ($manager) {
                ProjectMember::firstOrCreate(
                    ['crm_project_id' => $project->id, 'user_id' => $manager->id],
                    ['organization_id' => $project->organization_id, 'added_by' => $user->id, 'role' => 'manager', 'responsibility' => 'Project Manager', 'is_active' => true],
                );
                $this->activity->log($project, $user->id, 'manager_assigned', "{$manager->name} assigned as project manager.");
                $this->notifier->projectManagerAssigned($project, $manager, $user);
            }
        }

        return back()->with('success', 'Project updated.');
    }

    public function destroy(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('delete', $project);
        $name = $project->name;
        $project->delete();

        return redirect()->route('crm.projects.index')->with('success', "Project \"{$name}\" deleted.");
    }

    // ── Team ────────────────────────────────────────────────────────────────

    public function storeMember(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('manageTeam', $project);
        $user = $request->user();

        $validated = $request->validate([
            'user_id' => ['required', Rule::exists('users', 'id')->where('organization_id', $user->organization_id)],
            'role' => 'required|string|max:50',
            'responsibility' => 'nullable|string|max:255',
        ]);

        $member = ProjectMember::updateOrCreate(
            ['crm_project_id' => $project->id, 'user_id' => $validated['user_id']],
            [
                'organization_id' => $project->organization_id,
                'added_by' => $user->id,
                'role' => $validated['role'],
                'responsibility' => $validated['responsibility'] ?? null,
                'is_active' => true,
            ],
        );

        $added = User::find($validated['user_id']);
        $this->activity->log($project, $user->id, 'member_added', "{$added?->name} added to the team as {$validated['role']}.");
        if ($added) {
            $this->notifier->projectTeamMemberAdded($project, $added, $user);
        }

        return back()->with('success', 'Team member added.');
    }

    public function updateMember(Request $request, Project $project, ProjectMember $member): RedirectResponse
    {
        $this->authorize('manageTeam', $project);
        abort_unless($member->crm_project_id === $project->id, 404);

        $validated = $request->validate([
            'role' => 'required|string|max:50',
            'responsibility' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ]);
        $member->update($validated);

        $this->activity->log($project, $request->user()->id, 'member_updated', "{$member->user?->name}'s team membership was updated.");

        return back()->with('success', 'Team member updated.');
    }

    public function destroyMember(Request $request, Project $project, ProjectMember $member): RedirectResponse
    {
        $this->authorize('manageTeam', $project);
        abort_unless($member->crm_project_id === $project->id, 404);
        $user = $request->user();
        $removed = $member->user;

        $member->delete();

        $this->activity->log($project, $user->id, 'member_removed', "{$removed?->name} was removed from the team.");
        if ($removed) {
            $this->notifier->projectTeamMemberRemoved($project, $removed, $user);
        }

        return back()->with('success', 'Team member removed.');
    }

    // ── Milestones ──────────────────────────────────────────────────────────

    public function storeMilestone(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('manageTasks', $project);
        $user = $request->user();
        $data = $this->validateMilestone($request);

        $project->milestones()->create([
            ...$data,
            'organization_id' => $project->organization_id,
            'created_by' => $user->id,
            'sort_order' => (int) $project->milestones()->max('sort_order') + 1,
            'completed_at' => ($data['status'] ?? null) === MilestoneStatus::Completed->value ? now() : null,
        ]);

        $this->activity->log($project, $user->id, 'milestone_added', "Milestone \"{$data['title']}\" added.");

        return back()->with('success', 'Milestone added.');
    }

    public function updateMilestone(Request $request, Project $project, ProjectMilestone $milestone): RedirectResponse
    {
        $this->authorize('manageTasks', $project);
        abort_unless($milestone->crm_project_id === $project->id, 404);

        $data = $this->validateMilestone($request);
        if (($data['status'] ?? null) === MilestoneStatus::Completed->value && ! $milestone->completed_at) {
            $data['completed_at'] = now();
        } elseif (($data['status'] ?? null) && $data['status'] !== MilestoneStatus::Completed->value) {
            $data['completed_at'] = null;
        }
        $milestone->update($data);

        return back()->with('success', 'Milestone updated.');
    }

    public function destroyMilestone(Request $request, Project $project, ProjectMilestone $milestone): RedirectResponse
    {
        $this->authorize('manageTasks', $project);
        abort_unless($milestone->crm_project_id === $project->id, 404);
        $milestone->delete();

        return back()->with('success', 'Milestone deleted.');
    }

    // ── Notes ───────────────────────────────────────────────────────────────

    public function storeNote(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('view', $project);
        $user = $request->user();
        $validated = $request->validate(['body' => 'required|string|max:5000']);

        $project->projectNotes()->create([
            'organization_id' => $project->organization_id,
            'user_id' => $user->id,
            'body' => $validated['body'],
        ]);

        $this->activity->log($project, $user->id, 'note_added', 'A note was added.');

        return back()->with('success', 'Note added.');
    }

    public function destroyNote(Request $request, Project $project, ProjectNote $note): RedirectResponse
    {
        abort_unless($note->crm_project_id === $project->id, 404);
        $user = $request->user();
        // The author may delete their own note; leads/admins may delete any.
        abort_unless($note->user_id === $user->id || $user->can('manageTasks', $project), 403);
        $note->delete();

        return back()->with('success', 'Note deleted.');
    }

    // ── Files ───────────────────────────────────────────────────────────────

    public function storeFile(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('manageTasks', $project);
        $user = $request->user();

        $request->validate([
            'file' => 'required|file|max:102400|mimetypes:application/pdf,image/jpeg,image/png,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/plain,text/csv',
        ]);

        $file = $request->file('file');
        $stored = (string) Str::ulid().'.'.$file->getClientOriginalExtension();
        $path = $file->storeAs("crm-projects/{$project->id}", $stored, 'local');

        $project->files()->create([
            'organization_id' => $project->organization_id,
            'uploaded_by' => $user->id,
            'display_name' => $file->getClientOriginalName(),
            'original_filename' => $file->getClientOriginalName(),
            'stored_filename' => $stored,
            'disk' => 'local',
            'path' => $path,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'checksum' => hash_file('sha256', $file->getRealPath()),
            'source' => 'upload',
        ]);

        $this->activity->log($project, $user->id, 'file_uploaded', "File \"{$file->getClientOriginalName()}\" uploaded.");

        return back()->with('success', 'File uploaded.');
    }

    public function downloadFile(Request $request, Project $project, \App\Models\Crm\ProjectFile $file): mixed
    {
        $this->authorize('view', $project);
        abort_unless($file->crm_project_id === $project->id, 404);
        abort_unless(Storage::disk($file->disk)->exists($file->path), 404);

        return Storage::disk($file->disk)->download($file->path, $file->display_name);
    }

    public function destroyFile(Request $request, Project $project, \App\Models\Crm\ProjectFile $file): RedirectResponse
    {
        $this->authorize('manageTasks', $project);
        abort_unless($file->crm_project_id === $project->id, 404);

        Storage::disk($file->disk)->delete($file->path);
        $file->delete();

        $this->activity->log($project, $request->user()->id, 'file_removed', "File \"{$file->display_name}\" removed.");

        return back()->with('success', 'File removed.');
    }

    // ── Tasks ───────────────────────────────────────────────────────────────

    public function storeTask(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('manageTasks', $project);
        $user = $request->user();

        $task = $project->tasks()->create([
            ...$this->validateTask($request, $user->organization_id),
            'organization_id' => $user->organization_id,
            'created_by' => $user->id,
            'position' => (int) $project->tasks()->max('position') + 1,
        ]);

        $this->recomputeProgress($project);
        $this->activity->log($project, $user->id, 'task_added', "Task \"{$task->title}\" created.");
        if ($task->assigned_to && ($assignee = User::find($task->assigned_to))) {
            $this->notifier->projectTaskAssigned($project, $task, $assignee, $user);
        }

        return back()->with('success', 'Task added.');
    }

    public function updateTask(Request $request, Project $project, Task $task): RedirectResponse
    {
        abort_unless($task->crm_project_id === $project->id, 404);
        $this->authorize('updateTask', [$project, $task]);
        $user = $request->user();

        // A team member who isn't a lead may only move their own task's status.
        $isLead = $user->can('manageTasks', $project);
        if ($isLead) {
            $data = $this->validateTask($request, $user->organization_id);
        } else {
            $data = $request->validate(['status' => ['required', new Enum(TaskStatus::class)]]);
        }

        $previousAssignee = $task->assigned_to;

        if (($data['status'] ?? null) === TaskStatus::Completed->value && ! $task->completed_at) {
            $data['completed_at'] = now();
        } elseif (($data['status'] ?? null) && $data['status'] !== TaskStatus::Completed->value) {
            $data['completed_at'] = null;
        }
        $task->update($data);

        $this->recomputeProgress($project);

        if ($isLead && array_key_exists('assigned_to', $data) && $data['assigned_to'] && $data['assigned_to'] !== $previousAssignee) {
            if ($assignee = User::find($data['assigned_to'])) {
                $this->notifier->projectTaskAssigned($project, $task, $assignee, $user);
            }
        }

        return back()->with('success', 'Task updated.');
    }

    public function destroyTask(Request $request, Project $project, Task $task): RedirectResponse
    {
        $this->authorize('manageTasks', $project);
        abort_unless($task->crm_project_id === $project->id, 404);
        $task->delete();

        $this->recomputeProgress($project);

        return back()->with('success', 'Task deleted.');
    }

    public function storeTaskComment(Request $request, Project $project, Task $task): RedirectResponse
    {
        $this->authorize('view', $project);
        abort_unless($task->crm_project_id === $project->id, 404);
        $user = $request->user();

        $validated = $request->validate(['body' => 'required|string|max:5000']);
        TaskComment::create([
            'organization_id' => $project->organization_id,
            'crm_task_id' => $task->id,
            'user_id' => $user->id,
            'body' => $validated['body'],
        ]);

        return back()->with('success', 'Comment added.');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** Recompute % complete from task completion (silent — no audit churn). */
    private function recomputeProgress(Project $project): void
    {
        $total = $project->tasks()->count();
        $done = $project->tasks()->where('status', TaskStatus::Completed->value)->count();
        $project->progress = $total > 0 ? (int) round($done / $total * 100) : $project->progress;
        $project->saveQuietly();
    }

    /** @return array<string,mixed> */
    private function rowPayload(Project $p): array
    {
        return [
            'id' => $p->id,
            'name' => $p->name,
            'code' => $p->code,
            'project_number' => $p->project_number,
            'description' => $p->description,
            'status' => $p->status->value,
            'status_label' => $p->status->label(),
            'status_color' => $p->status->color(),
            'progress' => $p->progress,
            'budget' => $p->budget !== null ? (float) $p->budget : null,
            'start_date' => $p->start_date?->toDateString(),
            'due_date' => $p->due_date?->toDateString(),
            'company' => $p->company?->name,
            'company_id' => $p->company_id,
            'owner' => $p->owner?->name,
            'owner_id' => $p->owner_id,
            'manager' => $p->projectManager?->name,
            'manager_id' => $p->project_manager_id,
            'created_via' => $p->created_via,
            'from_proposal' => $p->proposal_submission_id !== null,
            'tasks_count' => $p->tasks_count,
            'completed_tasks_count' => $p->completed_tasks_count,
            'members_count' => $p->members_count,
            'updated_at' => $p->updated_at?->toIso8601String(),
        ];
    }

    /** @return array<string,mixed> */
    private function detailPayload(Project $project): array
    {
        return [
            'id' => $project->id,
            'name' => $project->name,
            'code' => $project->code,
            'project_number' => $project->project_number,
            'description' => $project->description,
            'notes' => $project->notes,
            'status' => $project->status->value,
            'status_label' => $project->status->label(),
            'status_color' => $project->status->color(),
            'progress' => $project->progress,
            'budget' => $project->budget !== null ? (float) $project->budget : null,
            'start_date' => $project->start_date?->toDateString(),
            'due_date' => $project->due_date?->toDateString(),
            'completed_at' => $project->completed_at?->toIso8601String(),
            'created_via' => $project->created_via,
            'company' => $project->company?->name,
            'company_id' => $project->company_id,
            'contact' => $project->contact ? trim($project->contact->first_name.' '.$project->contact->last_name) : null,
            'owner' => $project->owner?->name,
            'owner_id' => $project->owner_id,
            'manager' => $project->projectManager?->name,
            'manager_id' => $project->project_manager_id,
            'proposal' => $project->proposal ? [
                'id' => $project->proposal->id,
                'number' => $project->proposal->proposal_number,
                'name' => $project->proposal->project_name,
                'status' => $project->proposal->status instanceof \BackedEnum ? $project->proposal->status->value : $project->proposal->status,
            ] : null,
            'opportunity' => $project->opportunity ? [
                'id' => $project->opportunity->id,
                'title' => $project->opportunity->title,
            ] : null,
        ];
    }

    /** @return array<string,mixed> */
    private function taskPayload(Task $t, User $user, bool $canManageTasks): array
    {
        $priority = $t->priority instanceof \BackedEnum ? $t->priority : TaskPriority::tryFrom((string) $t->priority);

        return [
            'id' => $t->id,
            'title' => $t->title,
            'description' => $t->description,
            'status' => $t->status->value,
            'status_label' => $t->status->label(),
            'status_color' => $t->status->color(),
            'priority' => $priority?->value ?? 'medium',
            'priority_label' => $priority?->label() ?? 'Medium',
            'due_date' => $t->due_date?->toDateString(),
            'completed_at' => $t->completed_at?->toDateString(),
            'assigned_to' => $t->assigned_to,
            'assignee' => $t->assignee?->name,
            'can_update' => $canManageTasks || $t->assigned_to === $user->id,
            'comments' => $t->relationLoaded('comments') ? $t->comments->map(fn (TaskComment $c) => [
                'id' => $c->id,
                'body' => $c->body,
                'author' => $c->author?->name,
                'created_at' => $c->created_at?->toIso8601String(),
            ])->values() : [],
        ];
    }

    /** @return array<string,mixed> */
    private function financials(Project $project, \Illuminate\Support\Collection $invoices): array
    {
        $invoiced = (float) $invoices->where('kind', 'invoice')->sum('total');
        $paid = (float) $invoices->where('kind', 'invoice')->sum('amount_paid');
        $budget = $project->budget !== null ? (float) $project->budget : 0.0;

        return [
            'budget' => $budget,
            'invoiced' => $invoiced,
            'paid' => $paid,
            'outstanding' => round($invoiced - $paid, 2),
            'remaining_budget' => round($budget - $invoiced, 2),
        ];
    }

    /** @return array<string,mixed> */
    private function validateProject(Request $request): array
    {
        $user = $request->user();

        return $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50',
            'status' => ['nullable', new Enum(ProjectStatus::class)],
            'description' => 'nullable|string',
            'notes' => 'nullable|string',
            'start_date' => 'nullable|date',
            'due_date' => 'nullable|date',
            'budget' => 'nullable|numeric|min:0|max:9999999999999',
            'company_id' => ['nullable', Rule::exists('companies', 'id')->where('organization_id', $user->organization_id)],
            'contact_id' => ['nullable', Rule::exists('contacts', 'id')->where('organization_id', $user->organization_id)],
        ]);
    }

    /** @return array<string,mixed> */
    private function validateTask(Request $request, int $orgId): array
    {
        return $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => ['nullable', new Enum(TaskStatus::class)],
            'priority' => ['nullable', new Enum(TaskPriority::class)],
            'due_date' => 'nullable|date',
            'assigned_to' => ['nullable', Rule::exists('users', 'id')->where('organization_id', $orgId)],
        ]);
    }

    /** @return array<string,mixed> */
    private function validateMilestone(Request $request): array
    {
        return $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'due_date' => 'nullable|date',
            'status' => ['nullable', new Enum(MilestoneStatus::class)],
        ]);
    }

    private function validateOrgUser(Request $request, string $field): int
    {
        $request->validate([
            $field => [Rule::exists('users', 'id')->where('organization_id', $request->user()->organization_id)],
        ]);

        return (int) $request->input($field);
    }

    private function companies(int $orgId)
    {
        return Company::where('organization_id', $orgId)->orderBy('name')->get(['id', 'name']);
    }

    private function orgUsers(int $orgId)
    {
        return User::where('organization_id', $orgId)->where('is_active', true)->orderBy('name')->get(['id', 'name']);
    }

    private function statusOptions(): array
    {
        return collect(ProjectStatus::cases())
            ->map(fn ($s) => ['value' => $s->value, 'label' => $s->label(), 'color' => $s->color()])
            ->all();
    }

    /** @return array<int,array<string,mixed>> */
    private function awardableProposals(int $orgId): array
    {
        $linked = Project::where('organization_id', $orgId)
            ->whereNotNull('proposal_submission_id')->pluck('proposal_submission_id');

        return ProposalSubmission::where('organization_id', $orgId)
            ->whereIn('status', [\App\Enums\ProposalStatus::Awarded->value, \App\Enums\ProposalStatus::Completed->value])
            ->whereNotIn('id', $linked)
            ->orderByDesc('award_date')
            ->limit(50)
            ->get(['id', 'proposal_number', 'project_name'])
            ->map(fn ($p) => ['id' => $p->id, 'number' => $p->proposal_number, 'name' => $p->project_name])
            ->all();
    }
}
