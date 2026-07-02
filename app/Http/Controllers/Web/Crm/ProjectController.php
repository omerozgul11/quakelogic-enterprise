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
use App\Models\Crm\ProjectChecklist;
use App\Models\Crm\ProjectChecklistItem;
use App\Models\Crm\ProjectContact;
use App\Models\Crm\ProjectEquipment;
use App\Models\Crm\ProjectExecutionRecord;
use App\Models\Crm\ProjectFile;
use App\Models\Crm\ProjectFolder;
use App\Models\Crm\ProjectMember;
use App\Models\Crm\ProjectMilestone;
use App\Models\Crm\ProjectNote;
use App\Models\Crm\ProjectShipment;
use App\Models\Crm\ProjectSignoff;
use App\Models\Crm\ProjectSite;
use App\Models\Crm\ProjectTravel;
use App\Models\Crm\Task;
use App\Models\Crm\TaskComment;
use App\Models\ProposalSubmission;
use App\Models\User;
use App\Services\Crm\FieldBriefingService;
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
                ->orWhere('code', 'like', "%{$s}%")
                ->orWhere('reference_numbers', 'like', "%{$s}%")
                ->orWhere('poc_name', 'like', "%{$s}%")
                // Cross-entity: find a project by what's *in* it — a serial on the
                // box, a tracking number, a site or a site contact.
                ->orWhereHas('equipment', fn ($e) => $e->where(fn ($x) => $x
                    ->where('serial_number', 'like', "%{$s}%")->orWhere('model', 'like', "%{$s}%")
                    ->orWhere('asset_tag', 'like', "%{$s}%")->orWhere('name', 'like', "%{$s}%")))
                ->orWhereHas('shipments', fn ($sh) => $sh->where(fn ($x) => $x
                    ->where('tracking_number', 'like', "%{$s}%")->orWhere('crate_number', 'like', "%{$s}%")
                    ->orWhere('bill_of_lading', 'like', "%{$s}%")))
                ->orWhereHas('sites', fn ($si) => $si->where(fn ($x) => $x
                    ->where('name', 'like', "%{$s}%")->orWhere('address', 'like', "%{$s}%")))
                ->orWhereHas('siteContacts', fn ($c) => $c->where(fn ($x) => $x
                    ->where('name', 'like', "%{$s}%")->orWhere('phone', 'like', "%{$s}%")
                    ->orWhere('mobile', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%")))))
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

        return Inertia::render('Projects/Index', [
            'projects' => $projects,
            'filters' => $request->only(['search', 'status', 'owner', 'mine']),
            'stats' => $stats,
            'companies' => $this->companies($user->organization_id),
            'owners' => $this->orgUsers($user->organization_id),
            'statuses' => $this->statusOptions(),
            'awardableProposals' => $manageAll || $user->can('manage projects')
                ? $this->awardableProposals($user->organization_id) : [],
            'linkableProposals' => $manageAll || $user->can('manage projects')
                ? $this->linkableProposals($user->organization_id) : [],
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
            'proposal:id,proposal_number,project_name,status', 'opportunity:id,title,status', 'briefingAuthor:id,name',
            'tasks.assignee:id,name', 'tasks.comments.author:id,name',
            'members.user:id,name', 'members.addedBy:id,name',
            'milestones', 'projectNotes.author:id,name', 'files.uploader:id,name', 'folders',
            'activities.user:id,name', 'vendors', 'sites', 'siteContacts',
            'equipment.asset:id,asset_tag,name', 'shipments',
            'executionRecords.performer:id,name', 'checklists.items.doneBy:id,name', 'travel.traveler:id,name',
            'signoffs.executionRecord:id,title', 'signoffs.capturedBy:id,name',
            'purchaseOrders' => fn ($q) => $q->with('supplier:id,name')->orderByDesc('order_date'),
        ]);

        $invoices = Invoice::where('crm_project_id', $project->id)
            ->orderByDesc('issue_date')->get();

        $expenses = \App\Modules\ExpenseTracker\Models\Expense::where('crm_project_id', $project->id)
            ->with('category:id,name')
            ->orderByDesc('expense_date')->get();

        $canManageTasks = $user->can('manageTasks', $project);
        $canManageTeam = $user->can('manageTeam', $project);
        $canAdminister = $user->can('administer', $project);

        return Inertia::render('Projects/Show', [
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
            'files' => $this->documentsPayload($project),
            'folders' => $project->folders->map(fn (ProjectFolder $f) => ['id' => $f->id, 'name' => $f->name])->values(),
            'activities' => $project->activities->take(100)->map(fn ($a) => [
                'id' => $a->id,
                'action' => $a->action,
                'description' => $a->description,
                'user' => $a->user?->name,
                'created_at' => $a->created_at?->toIso8601String(),
            ])->values(),
            'vendors' => $project->vendors->map(fn (\App\Models\Crm\ProjectVendor $v) => [
                'id' => $v->id,
                'category' => $v->category->value,
                'category_label' => $v->category->label(),
                'category_color' => $v->category->color(),
                'company_name' => $v->company_name,
                'contact_name' => $v->contact_name,
                'phone' => $v->phone,
                'email' => $v->email,
                'notes' => $v->notes,
            ])->values(),
            'vendorCategories' => \App\Enums\Crm\ProjectVendorCategory::options(),
            'sites' => $project->sites->map(fn (ProjectSite $s) => $this->sitePayload($s))->values(),
            'siteContacts' => $project->siteContacts->map(fn (ProjectContact $c) => $this->contactPayload($c))->values(),
            'contactCategories' => \App\Enums\Crm\ProjectContactCategory::options(),
            'equipment' => $project->equipment->map(fn (ProjectEquipment $e) => $this->equipmentPayload($e))->values(),
            'shipments' => $project->shipments->map(fn (ProjectShipment $s) => $this->shipmentPayload($s))->values(),
            'shipmentStatuses' => \App\Enums\Crm\ProjectShipmentStatus::options(),
            'carriers' => collect(\App\Enums\Carrier::cases())->map(fn ($c) => ['value' => $c->value, 'label' => $c->label(), 'color' => $c->color()])->all(),
            'linkableAssets' => $this->linkableAssets($user, $project),
            'executionRecords' => $project->executionRecords->map(fn (ProjectExecutionRecord $r) => $this->executionRecordPayload($r))->values(),
            'executionTypes' => \App\Enums\Crm\ExecutionRecordType::options(),
            'executionStatuses' => \App\Enums\Crm\ExecutionRecordStatus::options(),
            'checklists' => $project->checklists->map(fn (ProjectChecklist $c) => $this->checklistPayload($c))->values(),
            'travel' => $project->travel->map(fn (ProjectTravel $t) => $this->travelPayload($t))->values(),
            'travelTypes' => \App\Enums\Crm\TravelType::options(),
            'signoffs' => $project->signoffs->map(fn (ProjectSignoff $s) => $this->signoffPayload($s))->values(),
            'signoffTypes' => \App\Enums\Crm\SignoffType::options(),
            'purchaseOrders' => $project->purchaseOrders->map(fn ($po) => $this->poPayload($po))->values(),
            'attachablePurchaseOrders' => $this->attachablePurchaseOrders($project),
            'canProcurement' => $user->can('access procurement'),
            'invoices' => $invoices->map(fn (Invoice $i) => [
                'id' => $i->id,
                'number' => $i->number,
                'kind' => $i->kind,
                'status' => $i->status instanceof \BackedEnum ? $i->status->value : $i->status,
                'total' => (float) $i->total,
                'amount_paid' => (float) $i->amount_paid,
                'balance' => (float) ($i->total - $i->amount_paid),
            ])->values(),
            'expenses' => $expenses->map(fn (\App\Modules\ExpenseTracker\Models\Expense $e) => [
                'id' => $e->id,
                'number' => $e->number,
                'vendor' => $e->vendor,
                'description' => $e->description,
                'category' => $e->category?->name,
                'amount' => (float) $e->amount,
                'currency' => $e->currency,
                'status' => $e->status instanceof \BackedEnum ? $e->status->value : $e->status,
                'status_label' => $e->status?->label(),
                'status_color' => $e->status?->color(),
                'expense_date' => $e->expense_date?->toDateString(),
            ])->values(),
            'expenseCategories' => \App\Modules\ExpenseTracker\Models\ExpenseCategory::where('organization_id', $user->organization_id)
                ->orderBy('name')->get(['id', 'name'])->map(fn ($c) => ['value' => $c->id, 'label' => $c->name]),
            'financials' => $this->financials($project, $invoices, $expenses),
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
                'addExpense' => $user->can('create', \App\Modules\ExpenseTracker\Models\Expense::class),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Project::class);
        $user = $request->user();

        // The dedicated "From Proposal" flow sets from_proposal=true. Older
        // /crm/projects callers only posted proposal_submission_id, so treat a
        // proposal-only request the same way while preserving named manual links.
        if (($request->boolean('from_proposal') || ! $request->filled('name')) && $request->filled('proposal_submission_id')) {
            $proposal = ProposalSubmission::where('organization_id', $user->organization_id)
                ->findOrFail($request->input('proposal_submission_id'));
            $project = $this->creation->createFromProposal($proposal, $user, automatic: false);

            return redirect()->route('projects.show', $project)->with('success', 'Project created from proposal.');
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
            'proposal_submission_id' => $this->resolveLinkedProposal($request, $user),
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

        return redirect()->route('projects.show', $project)->with('success', 'Project created.');
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

        return redirect()->route('projects.index')->with('success', "Project \"{$name}\" deleted.");
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

    // ── Vendor contacts (forklift, trucking, …) ──────────────────────────────

    public function storeVendor(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('manageTasks', $project);
        $user = $request->user();
        $data = $this->validateVendor($request);

        $vendor = $project->vendors()->create([
            ...$data,
            'organization_id' => $project->organization_id,
            'created_by' => $user->id,
        ]);

        $this->activity->log($project, $user->id, 'vendor_added', "Vendor \"{$vendor->company_name}\" added.");

        return back()->with('success', 'Vendor added.');
    }

    /** Create an expense linked to this project (Expense Tracker record). */
    public function storeExpense(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('create', \App\Modules\ExpenseTracker\Models\Expense::class);
        $user = $request->user();

        $data = $request->validate([
            'description' => ['required', 'string', 'max:255'],
            'vendor' => ['nullable', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:99999999999'],
            'currency' => ['nullable', 'string', 'size:3'],
            'expense_date' => ['nullable', 'date'],
            'expense_category_id' => ['nullable', \Illuminate\Validation\Rule::exists('expense_categories', 'id')->where('organization_id', $user->organization_id)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $expense = \App\Modules\ExpenseTracker\Models\Expense::create([
            'organization_id' => $project->organization_id,
            'created_by' => $user->id,
            'owner_id' => $user->id,
            'crm_project_id' => $project->id,
            'company_id' => $project->company_id,
            'number' => app(\App\Modules\ExpenseTracker\Services\ExpenseNumberService::class)->generate($project->organization_id),
            'description' => $data['description'],
            'vendor' => $data['vendor'] ?? null,
            'amount' => $data['amount'],
            'currency' => strtoupper($data['currency'] ?? 'USD'),
            'expense_date' => $data['expense_date'] ?? now()->toDateString(),
            'expense_category_id' => $data['expense_category_id'] ?? null,
            'notes' => $data['notes'] ?? null,
            'status' => \App\Modules\ExpenseTracker\Enums\ExpenseStatus::Draft->value,
        ]);

        $this->activity->log($project, $user->id, 'expense_added', "Expense \"{$expense->description}\" added ({$expense->currency} ".number_format((float) $expense->amount, 2).').');

        return back()->with('success', "Expense {$expense->number} added to the project.");
    }

    public function updateVendor(Request $request, Project $project, \App\Models\Crm\ProjectVendor $vendor): RedirectResponse
    {
        $this->authorize('manageTasks', $project);
        abort_unless($vendor->crm_project_id === $project->id, 404);

        $vendor->update($this->validateVendor($request));

        return back()->with('success', 'Vendor updated.');
    }

    public function destroyVendor(Request $request, Project $project, \App\Models\Crm\ProjectVendor $vendor): RedirectResponse
    {
        $this->authorize('manageTasks', $project);
        abort_unless($vendor->crm_project_id === $project->id, 404);
        $name = $vendor->company_name;
        $vendor->delete();

        $this->activity->log($project, $request->user()->id, 'vendor_removed', "Vendor \"{$name}\" removed.");

        return back()->with('success', 'Vendor removed.');
    }

    // ── Installation sites & site contacts (field briefing) ──────────────────

    public function storeSite(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('manageTasks', $project);
        $user = $request->user();
        $data = $this->validateSite($request);

        // The first site for a project is automatically the primary one.
        $makePrimary = ! $project->sites()->exists() || ! empty($data['is_primary']);

        $site = $project->sites()->create([
            ...$data,
            'is_primary' => $makePrimary,
            'organization_id' => $project->organization_id,
            'created_by' => $user->id,
        ]);

        if ($makePrimary) {
            $project->sites()->whereKeyNot($site->id)->update(['is_primary' => false]);
        }

        $this->activity->log($project, $user->id, 'site_added', "Site \"{$site->name}\" added.");

        return back()->with('success', 'Site added.');
    }

    public function updateSite(Request $request, Project $project, ProjectSite $site): RedirectResponse
    {
        $this->authorize('manageTasks', $project);
        abort_unless($site->crm_project_id === $project->id, 404);

        $data = $this->validateSite($request);
        $site->update($data);

        // Primary is only ever *set* through here (e.g. the "Make primary"
        // action) — setting one primary demotes the rest.
        if (! empty($data['is_primary'])) {
            $project->sites()->whereKeyNot($site->id)->update(['is_primary' => false]);
        }

        return back()->with('success', 'Site updated.');
    }

    public function destroySite(Request $request, Project $project, ProjectSite $site): RedirectResponse
    {
        $this->authorize('manageTasks', $project);
        abort_unless($site->crm_project_id === $project->id, 404);
        $name = $site->name;
        $wasPrimary = $site->is_primary;
        $site->delete();

        // Promote the next remaining site if we removed the primary one.
        if ($wasPrimary) {
            $project->sites()->orderBy('id')->first()?->update(['is_primary' => true]);
        }

        $this->activity->log($project, $request->user()->id, 'site_removed', "Site \"{$name}\" removed.");

        return back()->with('success', 'Site removed.');
    }

    public function storeContact(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('manageTasks', $project);
        $user = $request->user();
        $data = $this->validateContact($request, $project);

        $contact = $project->siteContacts()->create([
            ...$data,
            'organization_id' => $project->organization_id,
            'created_by' => $user->id,
        ]);

        $this->activity->log($project, $user->id, 'contact_added', "Contact \"{$contact->name}\" added.");

        return back()->with('success', 'Contact added.');
    }

    public function updateContact(Request $request, Project $project, ProjectContact $contact): RedirectResponse
    {
        $this->authorize('manageTasks', $project);
        abort_unless($contact->crm_project_id === $project->id, 404);

        $contact->update($this->validateContact($request, $project));

        return back()->with('success', 'Contact updated.');
    }

    public function destroyContact(Request $request, Project $project, ProjectContact $contact): RedirectResponse
    {
        $this->authorize('manageTasks', $project);
        abort_unless($contact->crm_project_id === $project->id, 404);
        $name = $contact->name;
        $contact->delete();

        $this->activity->log($project, $request->user()->id, 'contact_removed', "Contact \"{$name}\" removed.");

        return back()->with('success', 'Contact removed.');
    }

    // ── Equipment & shipments ─────────────────────────────────────────────────

    public function storeEquipment(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('manageTasks', $project);
        $user = $request->user();
        $data = $this->validateEquipment($request, $project, $user);

        $item = $project->equipment()->create([
            ...$data,
            'organization_id' => $project->organization_id,
            'created_by' => $user->id,
        ]);

        $this->activity->log($project, $user->id, 'equipment_added', "Equipment \"{$item->name}\" added.");

        return back()->with('success', 'Equipment added.');
    }

    public function updateEquipment(Request $request, Project $project, ProjectEquipment $equipment): RedirectResponse
    {
        $this->authorize('manageTasks', $project);
        abort_unless($equipment->crm_project_id === $project->id, 404);

        $equipment->update($this->validateEquipment($request, $project, $request->user()));

        return back()->with('success', 'Equipment updated.');
    }

    public function destroyEquipment(Request $request, Project $project, ProjectEquipment $equipment): RedirectResponse
    {
        $this->authorize('manageTasks', $project);
        abort_unless($equipment->crm_project_id === $project->id, 404);
        $name = $equipment->name;
        $equipment->delete();

        $this->activity->log($project, $request->user()->id, 'equipment_removed', "Equipment \"{$name}\" removed.");

        return back()->with('success', 'Equipment removed.');
    }

    public function storeShipment(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('manageTasks', $project);
        $user = $request->user();
        $data = $this->validateShipment($request);

        $shipment = $project->shipments()->create([
            ...$data,
            'organization_id' => $project->organization_id,
            'created_by' => $user->id,
        ]);

        $label = $shipment->tracking_number ?: ($shipment->crate_number ?: "#{$shipment->id}");
        $this->activity->log($project, $user->id, 'shipment_added', "Shipment {$label} added.");

        return back()->with('success', 'Shipment added.');
    }

    public function updateShipment(Request $request, Project $project, ProjectShipment $shipment): RedirectResponse
    {
        $this->authorize('manageTasks', $project);
        abort_unless($shipment->crm_project_id === $project->id, 404);

        $shipment->update($this->validateShipment($request));

        return back()->with('success', 'Shipment updated.');
    }

    public function destroyShipment(Request $request, Project $project, ProjectShipment $shipment): RedirectResponse
    {
        $this->authorize('manageTasks', $project);
        abort_unless($shipment->crm_project_id === $project->id, 404);
        $label = $shipment->tracking_number ?: ($shipment->crate_number ?: "#{$shipment->id}");
        $shipment->delete();

        $this->activity->log($project, $request->user()->id, 'shipment_removed', "Shipment {$label} removed.");

        return back()->with('success', 'Shipment removed.');
    }

    // ── Execution records (install / commissioning / training / …) ────────────

    public function storeExecutionRecord(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('manageTasks', $project);
        $user = $request->user();
        $data = $this->validateExecutionRecord($request, $project, $user);

        $record = $project->executionRecords()->create([
            ...$data,
            'organization_id' => $project->organization_id,
            'created_by' => $user->id,
        ]);

        $this->activity->log($project, $user->id, 'execution_record_added', "{$record->type->label()} record \"{$record->title}\" added.");

        return back()->with('success', 'Record added.');
    }

    public function updateExecutionRecord(Request $request, Project $project, ProjectExecutionRecord $record): RedirectResponse
    {
        $this->authorize('manageTasks', $project);
        abort_unless($record->crm_project_id === $project->id, 404);

        $record->update($this->validateExecutionRecord($request, $project, $request->user()));

        return back()->with('success', 'Record updated.');
    }

    public function destroyExecutionRecord(Request $request, Project $project, ProjectExecutionRecord $record): RedirectResponse
    {
        $this->authorize('manageTasks', $project);
        abort_unless($record->crm_project_id === $project->id, 404);
        $title = $record->title;
        $record->delete();

        $this->activity->log($project, $request->user()->id, 'execution_record_removed', "Record \"{$title}\" removed.");

        return back()->with('success', 'Record removed.');
    }

    // ── Checklists & items ────────────────────────────────────────────────────

    public function storeChecklist(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('manageTasks', $project);
        $user = $request->user();
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
        ]);

        $project->checklists()->create([
            ...$validated,
            'organization_id' => $project->organization_id,
            'created_by' => $user->id,
            'sort_order' => (int) $project->checklists()->max('sort_order') + 1,
        ]);

        return back()->with('success', 'Checklist added.');
    }

    public function updateChecklist(Request $request, Project $project, ProjectChecklist $checklist): RedirectResponse
    {
        $this->authorize('manageTasks', $project);
        abort_unless($checklist->crm_project_id === $project->id, 404);

        $checklist->update($request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
        ]));

        return back()->with('success', 'Checklist updated.');
    }

    public function destroyChecklist(Request $request, Project $project, ProjectChecklist $checklist): RedirectResponse
    {
        $this->authorize('manageTasks', $project);
        abort_unless($checklist->crm_project_id === $project->id, 404);
        $checklist->delete();

        return back()->with('success', 'Checklist removed.');
    }

    public function storeChecklistItem(Request $request, Project $project, ProjectChecklist $checklist): RedirectResponse
    {
        $this->authorize('manageTasks', $project);
        abort_unless($checklist->crm_project_id === $project->id, 404);
        $validated = $request->validate(['text' => 'required|string|max:1000']);

        $checklist->items()->create([
            'organization_id' => $project->organization_id,
            'text' => $validated['text'],
            'position' => (int) $checklist->items()->max('position') + 1,
        ]);

        return back()->with('success', 'Item added.');
    }

    public function updateChecklistItem(Request $request, Project $project, ProjectChecklist $checklist, ProjectChecklistItem $item): RedirectResponse
    {
        $this->authorize('manageTasks', $project);
        abort_unless($checklist->crm_project_id === $project->id && $item->crm_project_checklist_id === $checklist->id, 404);
        $user = $request->user();

        $validated = $request->validate([
            'text' => 'sometimes|required|string|max:1000',
            'is_done' => 'sometimes|boolean',
        ]);

        if (array_key_exists('is_done', $validated)) {
            $validated['done_by'] = $validated['is_done'] ? $user->id : null;
            $validated['done_at'] = $validated['is_done'] ? now() : null;
        }
        $item->update($validated);

        return back()->with('success', 'Item updated.');
    }

    public function destroyChecklistItem(Request $request, Project $project, ProjectChecklist $checklist, ProjectChecklistItem $item): RedirectResponse
    {
        $this->authorize('manageTasks', $project);
        abort_unless($checklist->crm_project_id === $project->id && $item->crm_project_checklist_id === $checklist->id, 404);
        $item->delete();

        return back()->with('success', 'Item removed.');
    }

    // ── Travel arrangements ───────────────────────────────────────────────────

    public function storeTravel(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('manageTasks', $project);
        $user = $request->user();
        $data = $this->validateTravel($request, $user);

        $travel = $project->travel()->create([
            ...$data,
            'organization_id' => $project->organization_id,
            'created_by' => $user->id,
        ]);

        $this->activity->log($project, $user->id, 'travel_added', "Travel \"{$travel->title}\" added.");

        return back()->with('success', 'Travel added.');
    }

    public function updateTravel(Request $request, Project $project, ProjectTravel $travel): RedirectResponse
    {
        $this->authorize('manageTasks', $project);
        abort_unless($travel->crm_project_id === $project->id, 404);

        $travel->update($this->validateTravel($request, $request->user()));

        return back()->with('success', 'Travel updated.');
    }

    public function destroyTravel(Request $request, Project $project, ProjectTravel $travel): RedirectResponse
    {
        $this->authorize('manageTasks', $project);
        abort_unless($travel->crm_project_id === $project->id, 404);
        $title = $travel->title;
        $travel->delete();

        $this->activity->log($project, $request->user()->id, 'travel_removed', "Travel \"{$title}\" removed.");

        return back()->with('success', 'Travel removed.');
    }

    // ── Digital sign-offs ─────────────────────────────────────────────────────

    public function storeSignoff(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('manageTasks', $project);
        $user = $request->user();
        $data = $this->validateSignoff($request, $project);

        $signoff = $project->signoffs()->create([
            ...$data,
            'organization_id' => $project->organization_id,
            'captured_by' => $user->id,
            'signed_at' => $data['signed_at'] ?? now(),
        ]);

        $this->activity->log($project, $user->id, 'signoff_recorded', "{$signoff->type->label()} sign-off by {$signoff->signer_name} recorded.");

        return back()->with('success', 'Sign-off recorded.');
    }

    public function destroySignoff(Request $request, Project $project, ProjectSignoff $signoff): RedirectResponse
    {
        $this->authorize('manageTasks', $project);
        abort_unless($signoff->crm_project_id === $project->id, 404);
        $who = $signoff->signer_name;
        $signoff->delete();

        $this->activity->log($project, $request->user()->id, 'signoff_removed', "Sign-off by {$who} removed.");

        return back()->with('success', 'Sign-off removed.');
    }

    // ── AI field briefing & printable Field Packet ───────────────────────────

    public function generateBriefing(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);
        $user = $request->user();

        $briefing = app(FieldBriefingService::class)->generate($project);
        $project->forceFill([
            'ai_briefing' => $briefing,
            'ai_briefing_generated_at' => now(),
            'ai_briefing_by' => $user->id,
        ])->saveQuietly();

        $this->activity->log($project, $user->id, 'briefing_generated', 'AI field briefing generated.');

        return back()->with('success', 'Field briefing generated.');
    }

    public function fieldPacket(Request $request, Project $project): \Symfony\Component\HttpFoundation\Response
    {
        $this->authorize('view', $project);

        $project->load([
            'company:id,name', 'owner:id,name', 'projectManager:id,name',
            'sites', 'siteContacts', 'equipment.shipment', 'shipments',
            'executionRecords.performer:id,name', 'checklists.items', 'travel.traveler:id,name',
            'signoffs.executionRecord:id,title',
        ]);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.field-packet', [
            'project' => $project,
            'generatedAt' => now(),
            'generatedBy' => $request->user()->name,
        ])->setPaper('letter');

        return $pdf->download('field-packet-'.($project->project_number ?: $project->id).'.pdf');
    }

    // ── Purchase orders (links to Procurement) ────────────────────────────────

    public function attachPurchaseOrder(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('manageTasks', $project);
        $user = $request->user();

        $validated = $request->validate([
            'purchase_order_id' => ['required', Rule::exists('procurement_purchase_orders', 'id')
                ->where('organization_id', $project->organization_id)],
        ]);

        $po = \App\Modules\Procurement\Models\PurchaseOrder::where('organization_id', $project->organization_id)
            ->findOrFail($validated['purchase_order_id']);
        $po->update(['crm_project_id' => $project->id]);

        $this->activity->log($project, $user->id, 'po_linked', "Purchase order {$po->number} linked.");

        return back()->with('success', 'Purchase order linked.');
    }

    public function detachPurchaseOrder(Request $request, Project $project, int $purchaseOrder): RedirectResponse
    {
        $this->authorize('manageTasks', $project);

        $po = \App\Modules\Procurement\Models\PurchaseOrder::where('organization_id', $project->organization_id)
            ->where('crm_project_id', $project->id)
            ->findOrFail($purchaseOrder);
        $po->update(['crm_project_id' => null]);

        $this->activity->log($project, $request->user()->id, 'po_unlinked', "Purchase order {$po->number} unlinked.");

        return back()->with('success', 'Purchase order unlinked.');
    }

    // ── Files ───────────────────────────────────────────────────────────────

    public function storeFile(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('manageTasks', $project);
        $user = $request->user();

        $validated = $request->validate([
            'file' => 'required|file|max:102400|mimetypes:application/pdf,image/jpeg,image/png,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/plain,text/csv',
            'crm_project_folder_id' => ['nullable', Rule::exists('crm_project_folders', 'id')->where('crm_project_id', $project->id)],
            'parent_file_id' => ['nullable', Rule::exists('crm_project_files', 'id')->where('crm_project_id', $project->id)],
        ]);

        $file = $request->file('file');
        $stored = (string) Str::ulid().'.'.$file->getClientOriginalExtension();
        $path = $file->storeAs("crm-projects/{$project->id}", $stored, 'local');

        $attributes = [
            'organization_id' => $project->organization_id,
            'crm_project_folder_id' => $validated['crm_project_folder_id'] ?? null,
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
            'version' => 1,
            'is_current_version' => true,
            'parent_file_id' => null,
        ];

        // Uploading a new version of an existing document keeps the prior
        // revisions (no overwrite) and carries the document's name + folder.
        if (! empty($validated['parent_file_id'])) {
            $parent = ProjectFile::where('crm_project_id', $project->id)->findOrFail($validated['parent_file_id']);
            $rootId = $parent->parent_file_id ?? $parent->id;
            $family = fn () => ProjectFile::where('crm_project_id', $project->id)
                ->where(fn ($q) => $q->where('id', $rootId)->orWhere('parent_file_id', $rootId));

            $attributes['parent_file_id'] = $rootId;
            $attributes['version'] = (int) $family()->max('version') + 1;
            $attributes['display_name'] = $parent->display_name;
            $attributes['crm_project_folder_id'] = $validated['crm_project_folder_id'] ?? $parent->crm_project_folder_id;

            $family()->where('is_current_version', true)->update(['is_current_version' => false]);
        }

        $created = $project->files()->create($attributes);

        $label = $created->version > 1 ? "New version (v{$created->version}) of \"{$created->display_name}\"" : "File \"{$created->display_name}\"";
        $this->activity->log($project, $user->id, 'file_uploaded', "{$label} uploaded.");

        return back()->with('success', $created->version > 1 ? 'New version uploaded.' : 'File uploaded.');
    }

    public function storeFolder(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('manageTasks', $project);
        $user = $request->user();
        $validated = $request->validate(['name' => 'required|string|max:255']);

        $project->folders()->create([
            'organization_id' => $project->organization_id,
            'created_by' => $user->id,
            'name' => $validated['name'],
            'sort_order' => (int) $project->folders()->max('sort_order') + 1,
        ]);

        return back()->with('success', 'Folder created.');
    }

    public function updateFolder(Request $request, Project $project, ProjectFolder $folder): RedirectResponse
    {
        $this->authorize('manageTasks', $project);
        abort_unless($folder->crm_project_id === $project->id, 404);
        $folder->update($request->validate(['name' => 'required|string|max:255']));

        return back()->with('success', 'Folder renamed.');
    }

    public function destroyFolder(Request $request, Project $project, ProjectFolder $folder): RedirectResponse
    {
        $this->authorize('manageTasks', $project);
        abort_unless($folder->crm_project_id === $project->id, 404);

        // Keep the files — just unfile them — then remove the folder.
        ProjectFile::where('crm_project_folder_id', $folder->id)->update(['crm_project_folder_id' => null]);
        $folder->delete();

        return back()->with('success', 'Folder removed.');
    }

    public function moveFile(Request $request, Project $project, ProjectFile $file): RedirectResponse
    {
        $this->authorize('manageTasks', $project);
        abort_unless($file->crm_project_id === $project->id, 404);
        $validated = $request->validate([
            'crm_project_folder_id' => ['nullable', Rule::exists('crm_project_folders', 'id')->where('crm_project_id', $project->id)],
        ]);

        // Move the whole version family together.
        $rootId = $file->parent_file_id ?? $file->id;
        ProjectFile::where('crm_project_id', $project->id)
            ->where(fn ($q) => $q->where('id', $rootId)->orWhere('parent_file_id', $rootId))
            ->update(['crm_project_folder_id' => $validated['crm_project_folder_id'] ?? null]);

        return back()->with('success', 'File moved.');
    }

    public function restoreFileVersion(Request $request, Project $project, ProjectFile $file): RedirectResponse
    {
        $this->authorize('manageTasks', $project);
        abort_unless($file->crm_project_id === $project->id, 404);

        $rootId = $file->parent_file_id ?? $file->id;
        ProjectFile::where('crm_project_id', $project->id)
            ->where(fn ($q) => $q->where('id', $rootId)->orWhere('parent_file_id', $rootId))
            ->update(['is_current_version' => false]);
        $file->update(['is_current_version' => true]);

        $this->activity->log($project, $request->user()->id, 'file_version_restored', "Restored v{$file->version} of \"{$file->display_name}\".");

        return back()->with('success', "Version v{$file->version} is now current.");
    }

    public function downloadFile(Request $request, Project $project, \App\Models\Crm\ProjectFile $file): mixed
    {
        $this->authorize('view', $project);
        abort_unless($file->crm_project_id === $project->id, 404);
        abort_unless(Storage::disk($file->disk)->exists($file->path), 404);

        return Storage::disk($file->disk)->download($file->path, $file->display_name);
    }

    public function destroyFile(Request $request, Project $project, ProjectFile $file): RedirectResponse
    {
        $this->authorize('manageTasks', $project);
        abort_unless($file->crm_project_id === $project->id, 404);

        $wasCurrent = $file->is_current_version;
        $rootId = $file->parent_file_id ?? $file->id;

        Storage::disk($file->disk)->delete($file->path);
        $file->delete();

        // If we removed the current version, promote the newest remaining one.
        if ($wasCurrent) {
            ProjectFile::where('crm_project_id', $project->id)
                ->where(fn ($q) => $q->where('id', $rootId)->orWhere('parent_file_id', $rootId))
                ->orderByDesc('version')->first()
                ?->update(['is_current_version' => true]);
        }

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
            'address' => $project->address,
            'poc_name' => $project->poc_name,
            'poc_role' => $project->poc_role,
            'poc_phone' => $project->poc_phone,
            'poc_email' => $project->poc_email,
            'reference_numbers' => $project->reference_numbers,
            'logistics' => $project->logistics,
            'specs' => $project->specs,
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
            'ai_briefing' => $project->ai_briefing,
            'ai_briefing_at' => $project->ai_briefing_generated_at?->toIso8601String(),
            'ai_briefing_by' => $project->briefingAuthor?->name,
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
    private function financials(Project $project, \Illuminate\Support\Collection $invoices, ?\Illuminate\Support\Collection $expenses = null): array
    {
        $invoiced = (float) $invoices->where('kind', 'invoice')->sum('total');
        $paid = (float) $invoices->where('kind', 'invoice')->sum('amount_paid');
        $budget = $project->budget !== null ? (float) $project->budget : 0.0;
        $spent = (float) ($expenses?->sum('amount') ?? 0);

        return [
            'budget' => $budget,
            'invoiced' => $invoiced,
            'paid' => $paid,
            'outstanding' => round($invoiced - $paid, 2),
            'remaining_budget' => round($budget - $invoiced, 2),
            'spent' => round($spent, 2),
            'margin' => round($invoiced - $spent, 2),
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
            'address' => 'nullable|string|max:1000',
            'poc_name' => 'nullable|string|max:255',
            'poc_role' => 'nullable|string|max:255',
            'poc_phone' => 'nullable|string|max:50',
            'poc_email' => 'nullable|email|max:255',
            'reference_numbers' => 'nullable|string|max:2000',
            'logistics' => 'nullable|string|max:5000',
            'specs' => 'nullable|string|max:20000',
            'start_date' => 'nullable|date',
            'due_date' => 'nullable|date',
            'budget' => 'nullable|numeric|min:0|max:9999999999999',
            'company_id' => ['nullable', Rule::exists('companies', 'id')->where('organization_id', $user->organization_id)],
            'contact_id' => ['nullable', Rule::exists('contacts', 'id')->where('organization_id', $user->organization_id)],
        ]);
    }

    /**
     * Validate an optional "Related proposal" link chosen on the manual form:
     * it must belong to the org and not already be linked to another project
     * (one project per proposal, matching the award automation's idempotency).
     */
    private function resolveLinkedProposal(Request $request, User $user): ?int
    {
        if (! $request->filled('proposal_submission_id')) {
            return null;
        }

        $request->validate([
            'proposal_submission_id' => [
                Rule::exists('proposal_submissions', 'id')->where('organization_id', $user->organization_id),
                Rule::unique('crm_projects', 'proposal_submission_id')->whereNull('deleted_at'),
            ],
        ]);

        return (int) $request->input('proposal_submission_id');
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

    /** @return array<string,mixed> */
    private function validateVendor(Request $request): array
    {
        return $request->validate([
            'category' => ['required', new Enum(\App\Enums\Crm\ProjectVendorCategory::class)],
            'company_name' => 'required|string|max:255',
            'contact_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'notes' => 'nullable|string|max:2000',
        ]);
    }

    /** @return array<string,mixed> */
    private function validateSite(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'is_primary' => 'nullable|boolean',
            'address' => 'nullable|string|max:1000',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'maps_url' => 'nullable|url|max:1000',
            'access_instructions' => 'nullable|string|max:5000',
            'loading_dock' => 'nullable|string|max:255',
            'parking' => 'nullable|string|max:255',
            'working_hours' => 'nullable|string|max:255',
            'gate_hours' => 'nullable|string|max:255',
            'security_requirements' => 'nullable|string|max:5000',
            'badge_required' => 'nullable|boolean',
            'escort_required' => 'nullable|boolean',
            'ppe_required' => 'nullable|string|max:500',
            'forklift_available' => 'nullable|boolean',
            'crane_available' => 'nullable|boolean',
            'internet_available' => 'nullable|boolean',
            'power_available' => 'nullable|boolean',
            'water_available' => 'nullable|boolean',
            'compressed_air_available' => 'nullable|boolean',
            'utilities_notes' => 'nullable|string|max:2000',
            'environmental_conditions' => 'nullable|string|max:2000',
            'hazards' => 'nullable|string|max:5000',
            'lockout_tagout' => 'nullable|string|max:2000',
            'high_voltage' => 'nullable|boolean',
            'confined_space' => 'nullable|boolean',
            'fall_protection' => 'nullable|boolean',
            'chemical_hazards' => 'nullable|string|max:2000',
            'emergency_assembly_point' => 'nullable|string|max:500',
            'nearest_hospital' => 'nullable|string|max:500',
            'hospital_phone' => 'nullable|string|max:50',
            'police_phone' => 'nullable|string|max:50',
            'fire_phone' => 'nullable|string|max:50',
            'site_safety_contact' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:5000',
        ]);
    }

    /** @return array<string,mixed> */
    private function validateContact(Request $request, Project $project): array
    {
        return $request->validate([
            'category' => ['required', new Enum(\App\Enums\Crm\ProjectContactCategory::class)],
            'name' => 'required|string|max:255',
            'title' => 'nullable|string|max:255',
            'company' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'mobile' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'preferred_contact_method' => 'nullable|in:phone,mobile,email,any',
            'availability' => 'nullable|string|max:255',
            'is_emergency' => 'nullable|boolean',
            'crm_project_site_id' => ['nullable', Rule::exists('crm_project_sites', 'id')->where('crm_project_id', $project->id)],
            'notes' => 'nullable|string|max:2000',
        ]);
    }

    /** @return array<string,mixed> */
    private function sitePayload(ProjectSite $s): array
    {
        return [
            'id' => $s->id,
            'name' => $s->name,
            'is_primary' => $s->is_primary,
            'address' => $s->address,
            'latitude' => $s->latitude !== null ? (float) $s->latitude : null,
            'longitude' => $s->longitude !== null ? (float) $s->longitude : null,
            'maps_url' => $s->maps_url,
            'access_instructions' => $s->access_instructions,
            'loading_dock' => $s->loading_dock,
            'parking' => $s->parking,
            'working_hours' => $s->working_hours,
            'gate_hours' => $s->gate_hours,
            'security_requirements' => $s->security_requirements,
            'badge_required' => $s->badge_required,
            'escort_required' => $s->escort_required,
            'ppe_required' => $s->ppe_required,
            'forklift_available' => $s->forklift_available,
            'crane_available' => $s->crane_available,
            'internet_available' => $s->internet_available,
            'power_available' => $s->power_available,
            'water_available' => $s->water_available,
            'compressed_air_available' => $s->compressed_air_available,
            'utilities_notes' => $s->utilities_notes,
            'environmental_conditions' => $s->environmental_conditions,
            'hazards' => $s->hazards,
            'lockout_tagout' => $s->lockout_tagout,
            'high_voltage' => $s->high_voltage,
            'confined_space' => $s->confined_space,
            'fall_protection' => $s->fall_protection,
            'chemical_hazards' => $s->chemical_hazards,
            'emergency_assembly_point' => $s->emergency_assembly_point,
            'nearest_hospital' => $s->nearest_hospital,
            'hospital_phone' => $s->hospital_phone,
            'police_phone' => $s->police_phone,
            'fire_phone' => $s->fire_phone,
            'site_safety_contact' => $s->site_safety_contact,
            'notes' => $s->notes,
        ];
    }

    /** @return array<string,mixed> */
    private function contactPayload(ProjectContact $c): array
    {
        return [
            'id' => $c->id,
            'category' => $c->category->value,
            'category_label' => $c->category->label(),
            'category_color' => $c->category->color(),
            'name' => $c->name,
            'title' => $c->title,
            'company' => $c->company,
            'phone' => $c->phone,
            'mobile' => $c->mobile,
            'email' => $c->email,
            'preferred_contact_method' => $c->preferred_contact_method,
            'availability' => $c->availability,
            'is_emergency' => $c->is_emergency,
            'crm_project_site_id' => $c->crm_project_site_id,
            'notes' => $c->notes,
        ];
    }

    /** @return array<string,mixed> */
    private function validateEquipment(Request $request, Project $project, User $user): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'product' => 'nullable|string|max:255',
            'model' => 'nullable|string|max:255',
            'revision' => 'nullable|string|max:100',
            'serial_number' => 'nullable|string|max:255',
            'firmware' => 'nullable|string|max:100',
            'software_version' => 'nullable|string|max:100',
            'asset_tag' => 'nullable|string|max:100',
            'quantity' => 'nullable|integer|min:1|max:1000000',
            'power' => 'nullable|string|max:100',
            'voltage' => 'nullable|string|max:100',
            'weight' => 'nullable|string|max:100',
            'dimensions' => 'nullable|string|max:255',
            'center_of_gravity' => 'nullable|string|max:255',
            'lift_points' => 'nullable|string|max:255',
            'rigging_instructions' => 'nullable|string|max:5000',
            'installation_location' => 'nullable|string|max:255',
            'calibration_status' => 'nullable|string|max:255',
            'calibration_due' => 'nullable|date',
            'warranty_status' => 'nullable|string|max:255',
            'warranty_expires' => 'nullable|date',
            'notes' => 'nullable|string|max:5000',
            'crm_project_shipment_id' => ['nullable', Rule::exists('crm_project_shipments', 'id')->where('crm_project_id', $project->id)],
            'asset_id' => ['nullable', Rule::exists('asset_assets', 'id')->where('organization_id', $user->organization_id)],
        ]);

        if (array_key_exists('quantity', $data)) {
            $data['quantity'] = $data['quantity'] ?: 1;
        }

        return $data;
    }

    /** @return array<string,mixed> */
    private function validateShipment(Request $request): array
    {
        $data = $request->validate([
            'direction' => 'nullable|in:inbound,outbound,internal',
            'carrier' => ['nullable', new Enum(\App\Enums\Carrier::class)],
            'service' => 'nullable|string|max:100',
            'tracking_number' => 'nullable|string|max:255',
            'status' => ['nullable', new Enum(\App\Enums\Crm\ProjectShipmentStatus::class)],
            'shipped_date' => 'nullable|date',
            'expected_arrival' => 'nullable|date',
            'arrived_date' => 'nullable|date',
            'crate_number' => 'nullable|string|max:100',
            'package_count' => 'nullable|integer|min:0|max:1000000',
            'pallet_info' => 'nullable|string|max:255',
            'weight' => 'nullable|string|max:100',
            'gross_weight' => 'nullable|string|max:100',
            'net_weight' => 'nullable|string|max:100',
            'shipping_weight' => 'nullable|string|max:100',
            'dimensions' => 'nullable|string|max:255',
            'bill_of_lading' => 'nullable|string|max:255',
            'packing_list' => 'nullable|string|max:255',
            'forklift_instructions' => 'nullable|string|max:2000',
            'lift_points' => 'nullable|string|max:2000',
            'shock_indicator' => 'nullable|in:none,intact,tripped',
            'tilt_indicator' => 'nullable|in:none,intact,tripped',
            'notes' => 'nullable|string|max:5000',
        ]);

        if (array_key_exists('status', $data)) {
            $data['status'] = $data['status'] ?: 'preparing';
        }
        if (array_key_exists('direction', $data)) {
            $data['direction'] = $data['direction'] ?: 'inbound';
        }

        return $data;
    }

    /** @return array<string,mixed> */
    private function equipmentPayload(ProjectEquipment $e): array
    {
        return [
            'id' => $e->id,
            'name' => $e->name,
            'product' => $e->product,
            'model' => $e->model,
            'revision' => $e->revision,
            'serial_number' => $e->serial_number,
            'firmware' => $e->firmware,
            'software_version' => $e->software_version,
            'asset_tag' => $e->asset_tag,
            'quantity' => $e->quantity,
            'power' => $e->power,
            'voltage' => $e->voltage,
            'weight' => $e->weight,
            'dimensions' => $e->dimensions,
            'center_of_gravity' => $e->center_of_gravity,
            'lift_points' => $e->lift_points,
            'rigging_instructions' => $e->rigging_instructions,
            'installation_location' => $e->installation_location,
            'calibration_status' => $e->calibration_status,
            'calibration_due' => $e->calibration_due?->toDateString(),
            'warranty_status' => $e->warranty_status,
            'warranty_expires' => $e->warranty_expires?->toDateString(),
            'crm_project_shipment_id' => $e->crm_project_shipment_id,
            'asset_id' => $e->asset_id,
            'asset' => $e->relationLoaded('asset') && $e->asset ? trim(($e->asset->asset_tag ? $e->asset->asset_tag.' — ' : '').$e->asset->name) : null,
            'notes' => $e->notes,
        ];
    }

    /** @return array<string,mixed> */
    private function shipmentPayload(ProjectShipment $s): array
    {
        $carrier = $s->carrier;
        $status = $s->status;

        return [
            'id' => $s->id,
            'direction' => $s->direction,
            'carrier' => $carrier?->value,
            'carrier_label' => $carrier?->label(),
            'carrier_color' => $carrier?->color(),
            'service' => $s->service,
            'tracking_number' => $s->tracking_number,
            'tracking_url' => ($carrier && $s->tracking_number) ? $carrier->trackingUrl($s->tracking_number) : null,
            'status' => $status?->value,
            'status_label' => $status?->label(),
            'status_color' => $status?->color(),
            'shipped_date' => $s->shipped_date?->toDateString(),
            'expected_arrival' => $s->expected_arrival?->toDateString(),
            'arrived_date' => $s->arrived_date?->toDateString(),
            'crate_number' => $s->crate_number,
            'package_count' => $s->package_count,
            'pallet_info' => $s->pallet_info,
            'weight' => $s->weight,
            'gross_weight' => $s->gross_weight,
            'net_weight' => $s->net_weight,
            'shipping_weight' => $s->shipping_weight,
            'dimensions' => $s->dimensions,
            'bill_of_lading' => $s->bill_of_lading,
            'packing_list' => $s->packing_list,
            'forklift_instructions' => $s->forklift_instructions,
            'lift_points' => $s->lift_points,
            'shock_indicator' => $s->shock_indicator,
            'tilt_indicator' => $s->tilt_indicator,
            'notes' => $s->notes,
        ];
    }

    /**
     * Assets the user may link equipment to — only when they can reach the Asset
     * Management module; empty otherwise so the picker simply doesn't appear.
     *
     * @return array<int,array{value:string,label:string}>
     */
    private function linkableAssets(User $user, Project $project): array
    {
        if (! $user->can('access assets')) {
            return [];
        }

        return \App\Modules\AssetManagement\Models\Asset::where('organization_id', $project->organization_id)
            ->orderBy('asset_tag')
            ->limit(500)
            ->get(['id', 'asset_tag', 'name'])
            ->map(fn ($a) => ['value' => (string) $a->id, 'label' => trim(($a->asset_tag ? $a->asset_tag.' — ' : '').$a->name)])
            ->all();
    }

    /** @return array<string,mixed> */
    private function validateExecutionRecord(Request $request, Project $project, User $user): array
    {
        $data = $request->validate([
            'type' => ['required', new Enum(\App\Enums\Crm\ExecutionRecordType::class)],
            'title' => 'required|string|max:255',
            'status' => ['nullable', new Enum(\App\Enums\Crm\ExecutionRecordStatus::class)],
            'scheduled_date' => 'nullable|date',
            'completed_date' => 'nullable|date',
            'summary' => 'nullable|string|max:10000',
            'outcome' => 'nullable|string|max:10000',
            'customer_visible' => 'nullable|boolean',
            'notes' => 'nullable|string|max:5000',
            'performed_by' => ['nullable', Rule::exists('users', 'id')->where('organization_id', $user->organization_id)],
            'crm_project_site_id' => ['nullable', Rule::exists('crm_project_sites', 'id')->where('crm_project_id', $project->id)],
        ]);

        if (array_key_exists('status', $data)) {
            $data['status'] = $data['status'] ?: 'scheduled';
        }

        return $data;
    }

    /** @return array<string,mixed> */
    private function executionRecordPayload(ProjectExecutionRecord $r): array
    {
        return [
            'id' => $r->id,
            'type' => $r->type->value,
            'type_label' => $r->type->label(),
            'type_color' => $r->type->color(),
            'title' => $r->title,
            'status' => $r->status->value,
            'status_label' => $r->status->label(),
            'status_color' => $r->status->color(),
            'scheduled_date' => $r->scheduled_date?->toDateString(),
            'completed_date' => $r->completed_date?->toDateString(),
            'performed_by' => $r->performed_by,
            'performer' => $r->performer?->name,
            'crm_project_site_id' => $r->crm_project_site_id,
            'summary' => $r->summary,
            'outcome' => $r->outcome,
            'customer_visible' => $r->customer_visible,
            'notes' => $r->notes,
        ];
    }

    /** @return array<string,mixed> */
    private function checklistPayload(ProjectChecklist $c): array
    {
        $items = $c->items->map(fn (ProjectChecklistItem $i) => [
            'id' => $i->id,
            'text' => $i->text,
            'is_done' => $i->is_done,
            'position' => $i->position,
            'done_by' => $i->doneBy?->name,
            'done_at' => $i->done_at?->toIso8601String(),
        ])->values();

        return [
            'id' => $c->id,
            'title' => $c->title,
            'description' => $c->description,
            'sort_order' => $c->sort_order,
            'items' => $items,
            'done_count' => $items->where('is_done', true)->count(),
            'total_count' => $items->count(),
        ];
    }

    /** @return array<string,mixed> */
    private function validateTravel(Request $request, User $user): array
    {
        return $request->validate([
            'type' => ['required', new Enum(\App\Enums\Crm\TravelType::class)],
            'title' => 'required|string|max:255',
            'status' => 'nullable|in:planned,booked,completed,cancelled',
            'traveler_id' => ['nullable', Rule::exists('users', 'id')->where('organization_id', $user->organization_id)],
            'traveler_name' => 'nullable|string|max:255',
            'provider' => 'nullable|string|max:255',
            'confirmation_number' => 'nullable|string|max:255',
            'start_at' => 'nullable|date',
            'end_at' => 'nullable|date',
            'from_location' => 'nullable|string|max:255',
            'to_location' => 'nullable|string|max:255',
            'cost' => 'nullable|numeric|min:0|max:99999999999',
            'currency' => 'nullable|string|size:3',
            'booking_url' => 'nullable|url|max:1000',
            'notes' => 'nullable|string|max:5000',
        ]);
    }

    /** @return array<string,mixed> */
    private function travelPayload(ProjectTravel $t): array
    {
        return [
            'id' => $t->id,
            'type' => $t->type->value,
            'type_label' => $t->type->label(),
            'type_color' => $t->type->color(),
            'title' => $t->title,
            'status' => $t->status,
            'traveler' => $t->traveler?->name ?? $t->traveler_name,
            'traveler_id' => $t->traveler_id,
            'traveler_name' => $t->traveler_name,
            'provider' => $t->provider,
            'confirmation_number' => $t->confirmation_number,
            'start_at' => $t->start_at?->toIso8601String(),
            'end_at' => $t->end_at?->toIso8601String(),
            'from_location' => $t->from_location,
            'to_location' => $t->to_location,
            'cost' => $t->cost !== null ? (float) $t->cost : null,
            'currency' => $t->currency,
            'booking_url' => $t->booking_url,
            'notes' => $t->notes,
        ];
    }

    /** @return array<string,mixed> */
    private function validateSignoff(Request $request, Project $project): array
    {
        return $request->validate([
            'type' => ['required', new Enum(\App\Enums\Crm\SignoffType::class)],
            'signer_name' => 'required|string|max:255',
            'signer_title' => 'nullable|string|max:255',
            'signer_email' => 'nullable|email|max:255',
            'statement' => 'nullable|string|max:2000',
            'signature_data' => ['nullable', 'string', 'max:5000000', 'starts_with:data:image/'],
            'signed_at' => 'nullable|date',
            'notes' => 'nullable|string|max:2000',
            'crm_project_execution_record_id' => ['nullable', Rule::exists('crm_project_execution_records', 'id')->where('crm_project_id', $project->id)],
        ]);
    }

    /** @return array<string,mixed> */
    private function signoffPayload(ProjectSignoff $s): array
    {
        return [
            'id' => $s->id,
            'type' => $s->type->value,
            'type_label' => $s->type->label(),
            'type_color' => $s->type->color(),
            'signer_name' => $s->signer_name,
            'signer_title' => $s->signer_title,
            'signer_email' => $s->signer_email,
            'statement' => $s->statement,
            'signature_data' => $s->signature_data,
            'signed_at' => $s->signed_at?->toIso8601String(),
            'captured_by' => $s->capturedBy?->name,
            'execution_record' => $s->executionRecord?->title,
            'crm_project_execution_record_id' => $s->crm_project_execution_record_id,
            'notes' => $s->notes,
        ];
    }

    /**
     * Group project files into one entry per document (its version family),
     * exposing the current version plus the full revision history.
     *
     * @return array<int,array<string,mixed>>
     */
    private function documentsPayload(Project $project): array
    {
        return $project->files
            ->groupBy(fn (ProjectFile $f) => $f->parent_file_id ?? $f->id)
            ->map(function ($family) {
                $current = $family->firstWhere('is_current_version', true) ?? $family->sortByDesc('version')->first();

                return [
                    'id' => $current->id,
                    'name' => $current->display_name,
                    'size' => $current->size,
                    'mime_type' => $current->mime_type,
                    'source' => $current->source,
                    'uploaded_by' => $current->uploader?->name,
                    'created_at' => $current->created_at?->toIso8601String(),
                    'folder_id' => $current->crm_project_folder_id,
                    'version' => $current->version,
                    'versions' => $family->sortByDesc('version')->values()->map(fn (ProjectFile $v) => [
                        'id' => $v->id,
                        'version' => $v->version,
                        'size' => $v->size,
                        'uploaded_by' => $v->uploader?->name,
                        'created_at' => $v->created_at?->toIso8601String(),
                        'is_current' => (bool) $v->is_current_version,
                    ])->all(),
                ];
            })
            ->sortByDesc(fn ($f) => $f['created_at'])
            ->values()
            ->all();
    }

    /** @return array<string,mixed> */
    private function poPayload(\App\Modules\Procurement\Models\PurchaseOrder $po): array
    {
        $status = $po->status instanceof \BackedEnum ? $po->status : null;

        return [
            'id' => $po->id,
            'number' => $po->number,
            'supplier' => $po->supplier?->name,
            'status' => $status?->value ?? (string) $po->status,
            'status_label' => $status?->label() ?? (string) $po->status,
            'status_color' => $status?->color() ?? 'gray',
            'total' => (float) $po->total,
            'currency' => $po->currency,
            'order_date' => $po->order_date?->toDateString(),
            'expected_date' => $po->expected_date?->toDateString(),
        ];
    }

    /**
     * Org purchase orders not yet linked to any project — candidates the user
     * can attach to this one.
     *
     * @return array<int,array<string,mixed>>
     */
    private function attachablePurchaseOrders(Project $project): array
    {
        return \App\Modules\Procurement\Models\PurchaseOrder::where('organization_id', $project->organization_id)
            ->whereNull('crm_project_id')
            ->with('supplier:id,name')
            ->orderByDesc('order_date')
            ->limit(100)
            ->get()
            ->map(fn ($po) => [
                'id' => $po->id,
                'number' => $po->number,
                'supplier' => $po->supplier?->name,
                'total' => (float) $po->total,
            ])
            ->all();
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
            ->limit(500)
            ->get(['id', 'proposal_number', 'project_name'])
            ->map(fn ($p) => ['id' => $p->id, 'number' => $p->proposal_number, 'name' => $p->project_name])
            ->all();
    }

    /**
     * Proposals (any status) not yet linked to a project — offered as the
     * "Related proposal" picker when creating a project manually.
     *
     * @return array<int,array{id:int,number:string,name:string,status:string}>
     */
    private function linkableProposals(int $orgId): array
    {
        $linked = Project::where('organization_id', $orgId)
            ->whereNotNull('proposal_submission_id')->pluck('proposal_submission_id');

        return ProposalSubmission::where('organization_id', $orgId)
            ->whereNotIn('id', $linked)
            ->orderByDesc('updated_at')
            ->limit(500)
            ->get(['id', 'proposal_number', 'project_name', 'status'])
            ->map(fn ($p) => [
                'id' => $p->id,
                'number' => $p->proposal_number,
                'name' => $p->project_name,
                'status' => $p->status instanceof \BackedEnum ? $p->status->value : (string) $p->status,
            ])
            ->all();
    }
}
