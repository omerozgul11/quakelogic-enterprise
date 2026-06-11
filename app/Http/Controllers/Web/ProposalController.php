<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AiAnalysis;
use App\Models\Company;
use App\Models\Opportunity;
use App\Models\ProposalSubmission;
use App\Models\ProposalTeamMember;
use App\Models\User;
use App\Enums\ProposalStatus;
use App\Services\Proposals\ProposalIntakeService;
use App\Services\Proposals\ProposalNumberService;
use App\Services\Proposals\ProposalWorkflowService;
use App\Services\Notifications\Notifier;
use App\Support\Currency;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProposalController extends Controller
{
    public function __construct(
        private readonly ProposalNumberService $numberService,
        private readonly ProposalWorkflowService $workflow,
        private readonly ProposalIntakeService $intake,
        private readonly Notifier $notifier,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', ProposalSubmission::class);

        $user = $request->user();
        $query = ProposalSubmission::forOrganization($user->organization_id)
            ->with(['owner:id,name', 'company:id,name', 'agency:id,name']);

        // Non-admin users see only their proposals unless they have 'view all proposals'
        if (!$user->can('view all proposals')) {
            $query->where(fn($q) => $q
                ->where('owner_id', $user->id)
                ->orWhere('proposal_manager_id', $user->id)
                ->orWhereHas('teamMembers', fn($tm) => $tm->where('user_id', $user->id))
            );
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn($q) => $q
                ->where('project_name', 'like', "%{$s}%")
                ->orWhere('proposal_number', 'like', "%{$s}%")
                ->orWhere('solicitation_number', 'like', "%{$s}%")
            );
        }

        // Sorting by name, company, owner, status, value, due date, or recency.
        $sortable = [
            'name' => 'project_name',
            'company' => 'company',
            'owner' => 'owner',
            'status' => 'status',
            'value' => 'proposal_value',
            'due_date' => 'due_date',
            'date' => 'created_at',
        ];
        $sortKey = array_key_exists((string) $request->input('sort'), $sortable) ? $request->input('sort') : 'date';
        $column = $sortable[$sortKey];
        $direction = $request->input('direction') === 'asc' ? 'asc' : 'desc';

        if ($column === 'owner') {
            $query->orderBy(User::select('name')->whereColumn('users.id', 'proposal_submissions.owner_id'), $direction);
        } elseif ($column === 'company') {
            $query->orderBy(Company::select('name')->whereColumn('companies.id', 'proposal_submissions.company_id'), $direction);
        } elseif ($column === 'due_date') {
            $query->orderByRaw('due_date IS NULL')->orderBy('due_date', $direction);
        } else {
            $query->orderBy($column, $direction);
        }

        return Inertia::render('Proposals/Index', [
            'proposals' => $query->paginate(25)->withQueryString(),
            'filters' => [
                ...$request->only(['status', 'search']),
                'sort' => $sortKey,
                'direction' => $direction,
            ],
            'statuses' => collect(ProposalStatus::cases())->map(fn($s) => ['value' => $s->value, 'label' => $s->label(), 'color' => $s->color()]),
            'can' => [
                'create' => $user->can('create proposals'),
                'delete' => $user->can('delete proposals'),
            ],
        ]);
    }

    public function board(Request $request): Response
    {
        $this->authorize('viewAny', ProposalSubmission::class);
        $user = $request->user();

        $query = ProposalSubmission::forOrganization($user->organization_id)
            ->with(['owner:id,name', 'company:id,name'])
            ->orderByDesc('updated_at');

        if (!$user->can('view all proposals')) {
            $query->where(fn ($q) => $q
                ->where('owner_id', $user->id)
                ->orWhere('proposal_manager_id', $user->id)
                ->orWhereHas('teamMembers', fn ($tm) => $tm->where('user_id', $user->id)));
        }

        $proposals = $query->get()->map(fn ($p) => [
            'id' => $p->id,
            'proposal_number' => $p->proposal_number,
            'project_name' => $p->project_name,
            'status' => $p->status instanceof \BackedEnum ? $p->status->value : $p->status,
            'value' => (float) $p->proposal_value,
            'currency' => $p->currency ?? Currency::DEFAULT,
            'due_date' => $p->due_date?->format('Y-m-d'),
            'company' => $p->company?->name,
            'owner' => $p->owner?->name,
        ]);

        return Inertia::render('Proposals/Board', [
            'proposals' => $proposals,
            'statuses' => collect(ProposalStatus::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label(), 'color' => $s->color()]),
            'can' => ['create' => $user->can('create proposals'), 'move' => $user->can('create proposals')],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', ProposalSubmission::class);
        $user = $request->user();

        return Inertia::render('Proposals/Create', [
            'opportunities' => Opportunity::where('organization_id', $user->organization_id)->active()->orderBy('title')->get(['id', 'title', 'solicitation_number']),
            'users' => User::where('organization_id', $user->organization_id)->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'currencies' => Currency::options(),
            'isAdmin' => $user->hasRole('Super Admin'),
            'currentUser' => ['id' => $user->id, 'name' => $user->name],
            'statuses' => collect(ProposalStatus::cases())->map(fn($s) => ['value' => $s->value, 'label' => $s->label()]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', ProposalSubmission::class);

        $validated = $request->validate([
            'project_name' => 'required|string|max:500',
            'opportunity_id' => 'nullable|exists:opportunities,id',
            'company' => 'nullable|string|max:255',
            'solicitation_number' => 'nullable|string|max:100',
            'proposal_value' => 'nullable|numeric|min:0',
            'currency' => ['nullable', Rule::in(Currency::codes())],
            'due_date' => 'nullable|date',
            'description' => 'nullable|string',
            'owner_id' => 'nullable|exists:users,id',
            'team_member_ids' => 'nullable|array',
            'team_member_ids.*' => 'integer|exists:users,id',
            // Security: only PDF and image files are accepted. Office/text formats
            // (doc, docx, xls, txt, csv, …) are rejected. Validate by sniffed
            // content type (mimetypes) and extension (mimes) for defense in depth.
            'document' => 'nullable|file|max:102400|mimetypes:application/pdf,image/jpeg,image/png|mimes:pdf,jpg,jpeg,png',
        ]);

        $user = $request->user();

        // Ownership is admin-controlled. Regular users always own their own
        // proposals and cannot assign someone else.
        $ownerId = $this->resolveOwnerId($validated['owner_id'] ?? null, $user, $user->id);

        $proposal = ProposalSubmission::create([
            ...collect($validated)->except(['document', 'company', 'currency', 'owner_id', 'team_member_ids'])->all(),
            'organization_id' => $user->organization_id,
            'created_by' => $user->id,
            'owner_id' => $ownerId,
            'proposal_manager_id' => $ownerId,
            'company_id' => $this->resolveCompanyId($validated['company'] ?? null, $user),
            'currency' => Currency::normalize($validated['currency'] ?? Currency::DEFAULT),
            'proposal_number' => $this->numberService->generate($user->organization_id),
            'status' => 'draft',
        ]);

        $proposal->statusHistory()->create([
            'changed_by' => $user->id,
            'from_status' => null,
            'to_status' => 'draft',
            'changed_at' => now(),
        ]);

        $this->syncTeamMembers($proposal, $validated['team_member_ids'] ?? [], $user);
        $this->ensureOwnerOnTeam($proposal, $user);

        // Optional attached document: QuakeAI reads it and fills any blanks.
        if ($request->hasFile('document')) {
            $analysis = $this->intake->extract($proposal, $request->file('document'), $user);
            if ($analysis) {
                $this->intake->autoApply($proposal->fresh(), $analysis, $user, fillBlanksOnly: true);
            }
        }

        $this->notifier->proposalCreated($proposal, $user);

        return redirect()->route('proposals.show', $proposal)->with('success', 'Proposal created.');
    }

    public function intake(Request $request): RedirectResponse
    {
        $this->authorize('create', ProposalSubmission::class);

        $request->validate([
            'documents' => 'required|array|min:1|max:15',
            // Security: only PDF and image files are accepted (see store()).
            'documents.*' => 'file|max:102400|mimetypes:application/pdf,image/jpeg,image/png|mimes:pdf,jpg,jpeg,png',
        ]);

        $user = $request->user();
        $files = array_values($request->file('documents'));

        $proposal = $this->intake->createDraft($files[0], $user);
        $this->ensureOwnerOnTeam($proposal, $user);

        // Extract from every dropped file; the most complete one fills the
        // proposal, and all files are stored as attachments.
        $analysis = $this->intake->extractBest($proposal, $files, $user);

        $this->notifier->proposalCreated($proposal, $user);

        $count = count($files);
        if (!$analysis) {
            return redirect()->route('proposals.show', $proposal)->with('warning', $count > 1
                ? "No readable text was found in those {$count} files — the proposal was created so you can fill it in manually."
                : 'No readable text was found in that file — the proposal was created so you can fill it in manually.');
        }

        $summary = $this->intake->autoApply($proposal, $analysis, $user);
        $message = $this->intakeMessage($summary);
        if ($count > 1) {
            $message .= " All {$count} files are attached.";
        }

        return redirect()->route('proposals.show', $proposal)->with('success', $message);
    }

    private function intakeMessage(array $summary): string
    {
        $parts = [];
        if (!empty($summary['fields'])) {
            $parts[] = count($summary['fields']) . ' field(s) filled';
        }
        $records = count($summary['created'] ?? []) + count($summary['linked'] ?? []);
        if ($records) {
            $parts[] = $records . ' record(s) created/linked';
        }
        return 'QuakeAI read your document — ' . (implode(', ', $parts) ?: 'review the details') . '. Everything below was filled in automatically.';
    }

    public function review(Request $request, ProposalSubmission $proposalSubmission): Response|RedirectResponse
    {
        $this->authorize('update', $proposalSubmission);

        $analysis = $this->latestExtraction($proposalSubmission);
        if (!$analysis) {
            return redirect()->route('proposals.show', $proposalSubmission);
        }

        $proposalSubmission->load('agency:id,name', 'company:id,name');

        return Inertia::render('Proposals/ReviewExtraction', [
            'proposal' => [
                'id' => $proposalSubmission->id,
                'proposal_number' => $proposalSubmission->proposal_number,
                'project_name' => $proposalSubmission->project_name,
            ],
            'changes' => $this->intake->proposedChanges($proposalSubmission, $analysis->output ?? []),
            'confidence' => $analysis->output['_extraction_confidence'] ?? null,
            'provider' => $analysis->ai_provider,
            'file' => $analysis->context_data['file'] ?? null,
        ]);
    }

    public function applyExtraction(Request $request, ProposalSubmission $proposalSubmission): RedirectResponse
    {
        $this->authorize('update', $proposalSubmission);

        $validated = $request->validate([
            'fields' => 'array',
            'fields.*' => 'string',
            'agency' => 'boolean',
            'company' => 'boolean',
            'contact' => 'boolean',
            'follow_up' => 'boolean',
        ]);

        $analysis = $this->latestExtraction($proposalSubmission);
        if (!$analysis) {
            return redirect()->route('proposals.show', $proposalSubmission);
        }

        $summary = $this->intake->apply($proposalSubmission, $analysis, $validated, $request->user());

        $applied = count($summary['fields']) + count($summary['created']) + count($summary['linked']);
        $message = $applied > 0
            ? 'Applied ' . count($summary['fields']) . ' field(s) and ' . (count($summary['created']) + count($summary['linked'])) . ' record(s). Review and edit below before saving.'
            : 'Nothing was applied — you can fill in the proposal below.';

        return redirect()->route('proposals.edit', $proposalSubmission)->with('success', $message);
    }

    private function latestExtraction(ProposalSubmission $proposal): ?AiAnalysis
    {
        return AiAnalysis::where('subject_type', ProposalSubmission::class)
            ->where('subject_id', $proposal->id)
            ->where('analysis_type', 'document_extraction')
            ->latest()
            ->first();
    }

    public function show(Request $request, ProposalSubmission $proposalSubmission): Response
    {
        $this->authorize('view', $proposalSubmission);

        $proposalSubmission->load([
            'opportunity:id,title,solicitation_number,due_date,estimated_value',
            'agency:id,name,email,phone',
            'company:id,name',
            'owner:id,name,email',
            'proposalManager:id,name,email',
            'statusHistory.changedBy:id,name',
            'teamMembers.user:id,name,email',
            'files.uploadedBy:id,name',
            'followUps' => fn ($q) => $q->with('contact:id,first_name,last_name,email,title')->latest('scheduled_date'),
            'notes.user:id,name',
            'complianceMatrices.items',
        ]);

        $user = $request->user();
        $stepNav = $this->workflow->stepNavigation($proposalSubmission->status);
        $canUpdate = $user->can('update', $proposalSubmission);

        $extraction = AiAnalysis::where('subject_type', ProposalSubmission::class)
            ->where('subject_id', $proposalSubmission->id)
            ->where('analysis_type', 'document_extraction')
            ->latest()
            ->first();

        return Inertia::render('Proposals/Show', [
            'proposal' => $proposalSubmission,
            'stepNav' => $stepNav,
            'currencies' => Currency::options(),
            'allowedTransitions' => $this->allowedTransitionList($proposalSubmission->status),
            'extraction' => $extraction ? [
                'output' => $extraction->output,
                'provider' => $extraction->ai_provider,
                'confidence' => $extraction->output['_extraction_confidence'] ?? null,
                'created_at' => $extraction->created_at?->toIso8601String(),
            ] : null,
            'can' => [
                'update' => $canUpdate,
                'edit' => $canUpdate,
                'upload' => $canUpdate || $user->can('manage proposal files'),
                'transition' => $canUpdate,
                'delete' => $user->can('delete', $proposalSubmission),
                'submit' => $user->can('submit', $proposalSubmission),
                'approve' => $user->can('approve', $proposalSubmission),
                'viewPrivate' => $user->can('viewPrivateDetails', $proposalSubmission),
                'manageFiles' => $user->can('manage proposal files'),
            ],
        ]);
    }

    public function edit(Request $request, ProposalSubmission $proposalSubmission): Response
    {
        $this->authorize('update', $proposalSubmission);
        $user = $request->user();
        $proposalSubmission->load('company:id,name', 'owner:id,name', 'teamMembers:id,proposal_submission_id,user_id');

        // Status choices for the edit form: the current status plus every status
        // it may legally transition to. tryFrom() drops any legacy transition
        // value that isn't a real enum case (e.g. 'under_evaluation').
        $statusOptions = collect([$proposalSubmission->status->value])
            ->merge(ProposalWorkflowService::ALLOWED_TRANSITIONS[$proposalSubmission->status->value] ?? [])
            ->unique()
            ->map(fn ($v) => ProposalStatus::tryFrom($v))
            ->filter()
            ->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()])
            ->values();

        return Inertia::render('Proposals/Edit', [
            'proposal' => [
                'id' => $proposalSubmission->id,
                'proposal_number' => $proposalSubmission->proposal_number,
                'project_name' => $proposalSubmission->project_name,
                'solicitation_number' => $proposalSubmission->solicitation_number,
                'company' => $proposalSubmission->company?->name ?? '',
                'proposal_value' => $proposalSubmission->proposal_value,
                'currency' => $proposalSubmission->currency ?? Currency::DEFAULT,
                'status' => $proposalSubmission->status->value,
                'due_date' => $proposalSubmission->due_date?->format('Y-m-d'),
                'owner_id' => $proposalSubmission->owner_id,
                'owner_name' => $proposalSubmission->owner?->name,
                'team_member_ids' => $proposalSubmission->teamMembers->pluck('user_id')->values(),
                'description' => $proposalSubmission->description,
                'scope_summary' => $proposalSubmission->scope_summary,
                'notes' => $proposalSubmission->notes,
                'submission_methods' => $proposalSubmission->submission_methods ?? [],
            ],
            'users' => User::where('organization_id', $user->organization_id)->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'currencies' => Currency::options(),
            'statusOptions' => $statusOptions,
            'isAdmin' => $user->hasRole('Super Admin'),
        ]);
    }

    public function update(Request $request, ProposalSubmission $proposalSubmission): RedirectResponse
    {
        $this->authorize('update', $proposalSubmission);

        $validated = $request->validate([
            'project_name' => 'required|string|max:500',
            'solicitation_number' => 'nullable|string|max:100',
            'company' => 'nullable|string|max:255',
            'proposal_value' => 'nullable|numeric|min:0',
            'award_value' => 'nullable|numeric|min:0',
            'currency' => ['nullable', Rule::in(Currency::codes())],
            'due_date' => 'nullable|date',
            'submission_date' => 'nullable|date',
            'award_date' => 'nullable|date',
            'description' => 'nullable|string',
            'scope_summary' => 'nullable|string',
            'notes' => 'nullable|string',
            'owner_id' => 'nullable|exists:users,id',
            'team_member_ids' => 'nullable|array',
            'team_member_ids.*' => 'integer|exists:users,id',
            'submission_methods' => 'nullable|array',
            'submission_methods.*' => 'string|in:mail,email,portal,fax,hand_delivery',
            'status' => ['nullable', Rule::in(collect(ProposalStatus::cases())->map(fn ($s) => $s->value)->all())],
        ]);

        $user = $request->user();

        // Status changes go through the workflow (FSM), never a direct column write.
        $updates = collect($validated)->except(['company', 'currency', 'owner_id', 'team_member_ids', 'status'])->all();
        $updates['submission_methods'] = array_values(array_unique($validated['submission_methods'] ?? []));
        $updates['currency'] = Currency::normalize($validated['currency'] ?? $proposalSubmission->currency);
        $updates['company_id'] = $this->resolveCompanyId($validated['company'] ?? null, $user);
        $updates['updated_by'] = $user->id;

        // Only admins may reassign ownership; for everyone else owner_id is
        // ignored entirely so users cannot change who owns a proposal.
        if ($user->hasRole('Super Admin') && !empty($validated['owner_id']) && $this->userInOrg((int) $validated['owner_id'], $user)) {
            $updates['owner_id'] = (int) $validated['owner_id'];
            $updates['proposal_manager_id'] = (int) $validated['owner_id'];
        }

        $proposalSubmission->update($updates);

        $proposalSubmission->refresh();
        $this->syncTeamMembers($proposalSubmission, $validated['team_member_ids'] ?? [], $user, replace: true);
        $this->ensureOwnerOnTeam($proposalSubmission, $user);

        // Apply a status change through the workflow if the form set one.
        $celebrate = null;
        $newStatus = $validated['status'] ?? null;
        if ($newStatus && $newStatus !== $proposalSubmission->status->value) {
            try {
                $this->workflow->transition($proposalSubmission, ProposalStatus::from($newStatus), $user, null);
                if ($newStatus === ProposalStatus::Submitted->value) {
                    $celebrate = $proposalSubmission->proposal_number;
                }
            } catch (\Illuminate\Validation\ValidationException $e) {
                return back()->withErrors($e->errors());
            }
        }

        $redirect = redirect()->route('proposals.show', $proposalSubmission)->with('success', 'Proposal updated.');
        if ($celebrate) {
            $redirect->with('celebrate', $celebrate);
        }

        return $redirect;
    }

    public function transition(Request $request, ProposalSubmission $proposalSubmission): RedirectResponse
    {
        $this->authorize('update', $proposalSubmission);

        $validated = $request->validate([
            'status' => 'required|string',
            'notes' => 'nullable|string',
        ]);

        try {
            $this->workflow->transition($proposalSubmission, ProposalStatus::from($validated['status']), $request->user(), $validated['notes'] ?? null);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        $redirect = redirect()->route('proposals.show', $proposalSubmission)->with('success', 'Proposal status updated.');

        // Submitting a proposal is worth celebrating — signal the Show page to
        // pop a confetti congratulations.
        if ($validated['status'] === ProposalStatus::Submitted->value) {
            $redirect->with('celebrate', $proposalSubmission->proposal_number);
        }

        return $redirect;
    }

    /**
     * Kanban move: change status and stay on the board (no redirect to the proposal).
     */
    public function move(Request $request, ProposalSubmission $proposalSubmission): RedirectResponse
    {
        $this->authorize('update', $proposalSubmission);
        $validated = $request->validate(['status' => 'required|string']);

        try {
            $this->workflow->transition($proposalSubmission, ProposalStatus::from($validated['status']), $request->user(), null);
        } catch (\Illuminate\Validation\ValidationException | \ValueError $e) {
            return back()->with('error', "That status change isn't allowed from the current stage.");
        }

        return back()->with('success', 'Status updated.');
    }

    public function destroy(Request $request, ProposalSubmission $proposalSubmission): RedirectResponse
    {
        $this->authorize('delete', $proposalSubmission);
        $proposalSubmission->delete();
        return redirect()->route('proposals.index')->with('success', 'Proposal deleted.');
    }

    /**
     * The full set of statuses this proposal may transition to next, for the
     * status dropdown on the detail page.
     *
     * @return array<int,array{value:string,label:string,color:string}>
     */
    private function allowedTransitionList(ProposalStatus $current): array
    {
        $allowed = ProposalWorkflowService::ALLOWED_TRANSITIONS[$current->value] ?? [];
        return collect(ProposalStatus::cases())
            ->filter(fn ($s) => in_array($s->value, $allowed, true))
            ->map(fn ($s) => ['value' => $s->value, 'label' => $s->label(), 'color' => $s->color()])
            ->values()
            ->all();
    }

    /**
     * Resolve the owner for a proposal. Only Super Admins may assign ownership
     * to someone else; everyone else falls back to the default (themselves).
     */
    private function resolveOwnerId(int|string|null $requested, User $actor, int $default): int
    {
        if ($requested && $actor->hasRole('Super Admin') && $this->userInOrg((int) $requested, $actor)) {
            return (int) $requested;
        }
        return $default;
    }

    private function userInOrg(int $userId, User $actor): bool
    {
        return User::where('id', $userId)->where('organization_id', $actor->organization_id)->exists();
    }

    /**
     * Find (case-insensitively) or create a Company by name within the actor's
     * organization. Returns null for an empty name, which clears the link.
     */
    private function resolveCompanyId(?string $name, User $actor): ?int
    {
        $name = trim((string) $name);
        if ($name === '') {
            return null;
        }

        $existing = Company::where('organization_id', $actor->organization_id)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->first();
        if ($existing) {
            return $existing->id;
        }

        return Company::create([
            'organization_id' => $actor->organization_id,
            'created_by' => $actor->id,
            'owner_id' => $actor->id,
            'name' => Str::limit($name, 250, ''),
        ])->id;
    }

    /**
     * Attach the given users as team members. When $replace is true, members no
     * longer selected are detached — except the owner, who always stays.
     *
     * @param  array<int,int|string>  $memberIds
     */
    private function syncTeamMembers(ProposalSubmission $proposal, array $memberIds, User $actor, bool $replace = false): void
    {
        $orgUserIds = User::where('organization_id', $proposal->organization_id)->pluck('id')->all();
        $valid = collect($memberIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => in_array($id, $orgUserIds, true))
            ->unique()
            ->values();

        if ($replace) {
            $keep = $valid->push($proposal->owner_id)->filter()->unique()->all();
            $proposal->teamMembers()->whereNotIn('user_id', $keep)->delete();
        }

        foreach ($valid as $userId) {
            ProposalTeamMember::firstOrCreate(
                ['proposal_submission_id' => $proposal->id, 'user_id' => $userId],
                ['role' => 'writer', 'assigned_by' => $actor->id],
            );
        }
    }

    /**
     * Guarantee the owner is always present in the team members list, tagged
     * with the 'owner' role.
     */
    private function ensureOwnerOnTeam(ProposalSubmission $proposal, User $actor): void
    {
        if (!$proposal->owner_id) {
            return;
        }

        $member = ProposalTeamMember::firstOrCreate(
            ['proposal_submission_id' => $proposal->id, 'user_id' => $proposal->owner_id],
            ['role' => 'owner', 'assigned_by' => $actor->id],
        );

        if ($member->role !== 'owner') {
            $member->update(['role' => 'owner']);
        }
    }

}
