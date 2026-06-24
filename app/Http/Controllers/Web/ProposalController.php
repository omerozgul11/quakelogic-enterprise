<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AiAnalysis;
use App\Models\Company;
use App\Models\Opportunity;
use App\Models\ProposalSection;
use App\Models\ProposalSubmission;
use App\Models\ProposalTeamMember;
use App\Models\User;
use App\Enums\CostCategory;
use App\Enums\ProposalStatus;
use App\Enums\ProposalType;
use App\Services\Ai\AiProviderInterface;
use App\Services\Proposals\ProposalDocumentService;
use App\Services\Proposals\ProposalIntakeService;
use App\Services\Proposals\ProposalMarginService;
use App\Services\Proposals\ProposalNumberService;
use App\Services\Proposals\ProposalWorkflowService;
use App\Services\Proposals\ProposalWriterService;
use App\Services\Notifications\Notifier;
use App\Support\Currency;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProposalController extends Controller
{
    /** Date filters are entered/displayed in Pacific time. */
    private const APP_TZ = 'America/Los_Angeles';

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

        // Non-admin users see only proposals they're involved in (creator,
        // owner, manager, or team member) unless they have 'view all proposals'.
        if (!$user->can('view all proposals')) {
            $query->where(fn($q) => $q
                ->where('created_by', $user->id)
                ->orWhere('owner_id', $user->id)
                ->orWhere('proposal_manager_id', $user->id)
                ->orWhereHas('teamMembers', fn($tm) => $tm->where('user_id', $user->id))
            );
        }

        // `status` accepts a single value or a comma-separated set, so dashboard
        // cards can deep-link to the exact status group their number counts.
        if ($request->filled('status')) {
            $statuses = array_values(array_filter(array_map('trim', explode(',', (string) $request->status))));
            $query->whereIn('status', $statuses);
        }
        if ($request->filled('type')) {
            $types = array_values(array_filter(array_map('trim', explode(',', (string) $request->type))));
            $query->whereIn('proposal_type', $types);
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn($q) => $q
                ->where('project_name', 'like', "%{$s}%")
                ->orWhere('proposal_number', 'like', "%{$s}%")
                ->orWhere('solicitation_number', 'like', "%{$s}%")
            );
        }
        $this->applyDateFilter($query, $request);

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

        // Overall profit-margin roll-up across the whole filtered set (USD).
        // Only proposals that have BOTH a bid and at least one cost line count —
        // a bid with no costs would otherwise read as 100% margin and skew it.
        $marginSet = (clone $query)
            ->without(['owner', 'company', 'agency'])
            ->reorder()
            ->select('proposal_submissions.id', 'proposal_submissions.proposal_value', 'proposal_submissions.currency')
            ->withSum('costs as estimated_cost', 'amount')
            ->get();

        $bidUsd = $costUsd = 0.0;
        $estimatedCount = 0;
        foreach ($marginSet as $m) {
            if ($m->estimated_cost === null || (float) $m->estimated_cost <= 0 || (float) $m->proposal_value <= 0) {
                continue;
            }
            $estimatedCount++;
            $bidUsd += Currency::toUsd((float) $m->proposal_value, $m->currency);
            $costUsd += Currency::toUsd((float) $m->estimated_cost, $m->currency);
        }
        $profitUsd = round($bidUsd - $costUsd, 2);
        $margins = [
            'bid' => round($bidUsd, 2),
            'cost' => round($costUsd, 2),
            'profit' => $profitUsd,
            'margin' => $bidUsd > 0 ? round($profitUsd / $bidUsd * 100, 1) : null,
            'count' => $estimatedCount,
            'currency' => Currency::DEFAULT,
        ];

        // Per-row cost total so the list can show each bid's margin at a glance.
        $query->withSum('costs as estimated_cost', 'amount');

        return Inertia::render('Proposals/Index', [
            'proposals' => $query->paginate(25)->withQueryString(),
            'filters' => [
                ...$request->only(['status', 'type', 'search', 'date_field', 'from', 'to']),
                'sort' => $sortKey,
                'direction' => $direction,
            ],
            'statuses' => collect(ProposalStatus::cases())->map(fn($s) => ['value' => $s->value, 'label' => $s->label(), 'color' => $s->color()]),
            'types' => ProposalType::options(),
            'margins' => $margins,
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

        // Base set this user may see; reused for the filter dropdown options so
        // they only list owners/companies actually present in that set.
        $base = ProposalSubmission::forOrganization($user->organization_id);
        if (!$user->can('view all proposals')) {
            $base->where(fn ($q) => $q
                ->where('created_by', $user->id)
                ->orWhere('owner_id', $user->id)
                ->orWhere('proposal_manager_id', $user->id)
                ->orWhereHas('teamMembers', fn ($tm) => $tm->where('user_id', $user->id)));
        }

        $ownerOptions = User::whereIn('id', (clone $base)->distinct()->pluck('owner_id')->filter())
            ->orderBy('name')->get(['id', 'name'])
            ->map(fn ($o) => ['value' => (string) $o->id, 'label' => $o->name])->values();
        $companyOptions = Company::whereIn('id', (clone $base)->whereNotNull('company_id')->distinct()->pluck('company_id'))
            ->orderBy('name')->get(['id', 'name'])
            ->map(fn ($c) => ['value' => (string) $c->id, 'label' => $c->name])->values();

        $query = (clone $base)->with(['owner:id,name', 'company:id,name'])->withCount('files')->orderByDesc('updated_at');

        if ($request->filled('status')) {
            $query->whereIn('status', array_values(array_filter(array_map('trim', explode(',', (string) $request->status)))));
        }
        if ($request->filled('owner_id')) {
            $query->where('owner_id', $request->owner_id);
        }
        if ($request->filled('company_id')) {
            $query->where('company_id', $request->company_id);
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn ($q) => $q
                ->where('project_name', 'like', "%{$s}%")
                ->orWhere('proposal_number', 'like', "%{$s}%")
                ->orWhere('solicitation_number', 'like', "%{$s}%"));
        }
        $this->applyDateFilter($query, $request);

        // Single query for the filtered set; total value is summed in USD across
        // each proposal's native currency so the figure is comparable.
        $models = $query->get();
        $totalValue = $models->reduce(fn ($carry, $p) => $carry + Currency::toUsd((float) $p->proposal_value, $p->currency), 0.0);

        $health = app(\App\Services\Proposals\ProposalHealthService::class);

        $proposals = $models->map(fn ($p) => [
            'id' => $p->id,
            'proposal_number' => $p->proposal_number,
            'project_name' => $p->project_name,
            'status' => $p->status instanceof \BackedEnum ? $p->status->value : $p->status,
            'value' => (float) $p->proposal_value,
            'currency' => $p->currency ?? Currency::DEFAULT,
            'due_date' => $p->due_date?->format('Y-m-d'),
            'submission_date' => $p->submission_date?->format('Y-m-d'),
            'documents' => (int) $p->files_count,
            'company' => $p->company?->name,
            'owner' => $p->owner?->name,
            'health' => $health->health($p),
        ])->values();

        return Inertia::render('Proposals/Board', [
            'proposals' => $proposals,
            'statuses' => collect(ProposalStatus::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label(), 'color' => $s->color()]),
            'owners' => $ownerOptions,
            'companies' => $companyOptions,
            'filters' => $request->only(['status', 'owner_id', 'company_id', 'search', 'date_field', 'from', 'to']),
            'totals' => ['count' => $models->count(), 'value' => round($totalValue, 2)],
            'can' => ['create' => $user->can('create proposals'), 'move' => $user->can('create proposals')],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', ProposalSubmission::class);
        $user = $request->user();

        // Optional due-date prefill (e.g. when adding from the calendar).
        $due = $request->query('due');
        $due = is_string($due) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $due) ? $due : null;

        return Inertia::render('Proposals/Create', [
            'opportunities' => Opportunity::where('organization_id', $user->organization_id)->active()->orderBy('title')->get(['id', 'title', 'solicitation_number']),
            'users' => User::where('organization_id', $user->organization_id)->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'currencies' => Currency::options(),
            'isAdmin' => $user->hasRole('Super Admin'),
            'currentUser' => ['id' => $user->id, 'name' => $user->name],
            'statuses' => collect(ProposalStatus::cases())->map(fn($s) => ['value' => $s->value, 'label' => $s->label()]),
            'proposalTypes' => ProposalType::options(),
            'prefill' => ['due_date' => $due],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', ProposalSubmission::class);

        $validated = $request->validate([
            'project_name' => 'required|string|max:500',
            'proposal_type' => ['nullable', Rule::in(collect(ProposalType::cases())->map(fn ($t) => $t->value)->all())],
            'opportunity_id' => 'nullable|exists:opportunities,id',
            'company' => 'required|string|max:255',
            'solicitation_number' => 'required|string|max:100',
            // Required for every type except RFIs, which carry no dollar value.
            'proposal_value' => ['required_unless:proposal_type,rfi', 'nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', Rule::in(Currency::codes())],
            'due_date' => 'required|date',
            'submission_methods' => 'nullable|array',
            'submission_methods.*' => 'string|in:mail,email,portal',
            'submission_portal_url' => 'nullable|string|max:2048',
            'description' => 'nullable|string',
            'owner_id' => 'nullable|exists:users,id',
            'team_member_ids' => 'nullable|array',
            'team_member_ids.*' => 'integer|exists:users,id',
            // Security: only PDF and image files are accepted. Office/text formats
            // (doc, docx, xls, txt, csv, …) are rejected. Validate by sniffed
            // content type (mimetypes) and extension (mimes) for defense in depth.
            'documents' => 'nullable|array|max:15',
            'documents.*' => 'file|max:102400|mimetypes:application/pdf,image/jpeg,image/png|mimes:pdf,jpg,jpeg,png',
        ], [
            'company.required' => 'Enter the client / company name.',
            'solicitation_number.required' => 'Enter the solicitation number.',
            'proposal_value.required_unless' => 'Enter the proposal value.',
            'due_date.required' => 'Select a due date.',
        ]);

        $user = $request->user();

        // Ownership is admin-controlled. Regular users always own their own
        // proposals and cannot assign someone else.
        $ownerId = $this->resolveOwnerId($validated['owner_id'] ?? null, $user, $user->id);

        $type = ProposalType::tryFrom($validated['proposal_type'] ?? '') ?? ProposalType::Proposal;

        $proposal = ProposalSubmission::create([
            ...collect($validated)->except(['documents', 'company', 'currency', 'owner_id', 'team_member_ids', 'proposal_type', 'proposal_value', 'submission_portal_url'])->all(),
            'submission_portal_url' => $this->normalizePortalUrl($validated),
            'proposal_type' => $type->value,
            // RFIs carry no dollar value.
            'proposal_value' => $type->hasValue() ? ($validated['proposal_value'] ?? null) : null,
            'organization_id' => $user->organization_id,
            'created_by' => $user->id,
            'owner_id' => $ownerId,
            'proposal_manager_id' => $ownerId,
            'company_id' => $this->resolveCompanyId($validated['company'] ?? null, $user),
            'currency' => Currency::normalize($validated['currency'] ?? Currency::DEFAULT),
            'proposal_number' => $this->numberService->generate($user->organization_id),
            'status' => 'in_progress',
        ]);

        $proposal->statusHistory()->create([
            'changed_by' => $user->id,
            'from_status' => null,
            'to_status' => 'in_progress',
            'changed_at' => now(),
        ]);

        $this->syncTeamMembers($proposal, $validated['team_member_ids'] ?? [], $user);
        $this->ensureOwnerOnTeam($proposal, $user);

        // Optional attached documents: all are stored on the proposal, and
        // QuakeAI reads them to fill any blanks (the richest one wins).
        if ($request->hasFile('documents')) {
            $files = array_values($request->file('documents'));
            $analysis = $this->intake->extractBest($proposal, $files, $user);
            if ($analysis) {
                $this->intake->autoApply($proposal->fresh(), $analysis, $user, fillBlanksOnly: true);
            }
        }

        $this->notifier->proposalCreated($proposal, $user);

        // If an admin created this on someone else's behalf, let the new owner know.
        if ($ownerId !== $user->id && ($newOwner = User::find($ownerId))) {
            $this->notifier->proposalAssigned($proposal, $newOwner, $user);
        }

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

    /**
     * Proposal Writer "start from documents": create a proposal from the dropped
     * bid/spec/solicitation files (same intake pipeline as `intake`), attaching
     * every file, then return the new proposal id as JSON so the writer can open
     * it and auto-draft the document. Drafting itself runs through the per-section
     * draft endpoints (client-orchestrated) so it stays within AI rate limits.
     */
    public function intakeDraft(Request $request): JsonResponse
    {
        $this->authorize('create', ProposalSubmission::class);

        $request->validate([
            'documents' => 'required|array|min:1|max:15',
            'documents.*' => 'file|max:102400|mimetypes:application/pdf,image/jpeg,image/png|mimes:pdf,jpg,jpeg,png',
        ]);

        $user = $request->user();
        $files = array_values($request->file('documents'));

        $proposal = $this->intake->createDraft($files[0], $user);
        $this->ensureOwnerOnTeam($proposal, $user);

        $analysis = $this->intake->extractBest($proposal, $files, $user);
        $this->notifier->proposalCreated($proposal, $user);

        if ($analysis) {
            $this->intake->autoApply($proposal, $analysis, $user);
        }

        return response()->json([
            'proposal_id' => $proposal->id,
            'extracted' => (bool) $analysis,
            'files' => count($files),
        ]);
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
            'mailing',
            'contract.milestones',
            'costs',
        ]);

        $user = $request->user();
        $stepNav = $this->workflow->stepNavigation($proposalSubmission->status);
        $canUpdate = $user->can('update', $proposalSubmission);

        $extraction = AiAnalysis::where('subject_type', ProposalSubmission::class)
            ->where('subject_id', $proposalSubmission->id)
            ->where('analysis_type', 'document_extraction')
            ->latest()
            ->first();

        $mailing = $proposalSubmission->mailing;

        $contract = $proposalSubmission->contract;

        return Inertia::render('Proposals/Show', [
            'proposal' => $proposalSubmission,
            // Who added this proposal to the platform, and when (created_at is on
            // the model). Fetched separately to avoid a relation/column name clash.
            'createdBy' => User::select('id', 'name')->find($proposalSubmission->created_by),
            'health' => app(\App\Services\Proposals\ProposalHealthService::class)->health($proposalSubmission),
            // Phase 17 — submission readiness checklist + score.
            'readiness' => app(\App\Services\Proposals\ProposalReadinessService::class)->evaluate($proposalSubmission),
            // Phase 5 — contract / financial lifecycle panel.
            'contract' => $contract ? [
                'id' => $contract->id,
                'contract_number' => $contract->contract_number,
                'po_number' => $contract->po_number,
                'invoice_number' => $contract->invoice_number,
                'stage' => $contract->stage->value,
                'stage_label' => $contract->stage->label(),
                'payment_status' => $contract->payment_status->value,
                'payment_label' => $contract->payment_status->label(),
                'contract_value' => $contract->contract_value !== null ? (float) $contract->contract_value : null,
                'amount_invoiced' => $contract->amount_invoiced !== null ? (float) $contract->amount_invoiced : null,
                'amount_paid' => $contract->amount_paid !== null ? (float) $contract->amount_paid : null,
                'currency' => $contract->currency ?? Currency::DEFAULT,
                'signed_at' => $contract->signed_at?->format('Y-m-d'),
                'po_received_at' => $contract->po_received_at?->format('Y-m-d'),
                'invoice_sent_at' => $contract->invoice_sent_at?->format('Y-m-d'),
                'paid_at' => $contract->paid_at?->format('Y-m-d'),
                'notes' => $contract->notes,
                'milestones' => $contract->milestones->map(fn ($m) => [
                    'id' => $m->id,
                    'title' => $m->title,
                    'due_date' => $m->due_date?->format('Y-m-d'),
                    'completed_at' => $m->completed_at?->format('Y-m-d'),
                ])->values(),
            ] : null,
            'contractOptions' => [
                'stages' => app(ContractController::class)->stageOptions(),
                'paymentStatuses' => app(ContractController::class)->paymentOptions(),
            ],
            'stepNav' => $stepNav,
            'countdown' => $this->countdown($proposalSubmission),
            'proposalTypes' => ProposalType::options(),
            // Cost / profit-margin estimate (bid vs. direct costs).
            'costs' => $proposalSubmission->costs->map(fn ($c) => [
                'id' => $c->id,
                'description' => $c->description,
                'category' => $c->category->value,
                'category_label' => $c->category->label(),
                'amount' => (float) $c->amount,
            ])->values(),
            'margin' => app(ProposalMarginService::class)->summary($proposalSubmission),
            'costCategories' => CostCategory::options(),
            'proposalSections' => ProposalWriterService::options(),
            'savedSections' => $proposalSubmission->sections()->get()->map(fn ($s) => [
                'id' => $s->id,
                'section_key' => $s->section_key,
                'heading' => $s->heading,
                'content' => $s->content,
            ])->values(),
            'currencies' => Currency::options(),
            // Shipments two-way link: surface the mailed-proposal delivery status.
            'mailTracking' => [
                'canAccess' => $user->can('access shipments'),
                'isMailed' => in_array('mail', (array) $proposalSubmission->submission_methods, true),
                'mailing' => $mailing ? [
                    'ulid' => $mailing->ulid,
                    'ups_tracking_number' => $mailing->ups_tracking_number,
                    'status_label' => $mailing->status->label(),
                    'status_color' => $mailing->status->color(),
                    'risk_label' => $mailing->risk()->label(),
                    'risk_color' => $mailing->risk()->color(),
                    'deadline' => optional($mailing->deadline)->toDateString(),
                    'scheduled_delivery' => optional($mailing->scheduled_delivery)->toDateString(),
                    'delivered_at' => optional($mailing->delivered_at)->toIso8601String(),
                    'received_by' => $mailing->received_by,
                    'proof_url' => $mailing->proof_url,
                ] : null,
                // Unlinked shipments the user may attach to this proposal (only
                // surfaced when they have shipment access and none is linked yet).
                'linkableShipments' => ($user->can('access shipments') && ! $mailing)
                    ? \App\Models\ProposalMailing::where('organization_id', $proposalSubmission->organization_id)
                        ->whereNull('proposal_submission_id')
                        ->latest()
                        ->limit(50)
                        ->get()
                        ->map(fn ($m) => [
                            'ulid' => $m->ulid,
                            'label' => trim($m->ups_tracking_number . ' · ' . $m->status->label() . ($m->recipient_name ? ' · ' . $m->recipient_name : '')),
                        ])->all()
                    : [],
            ],
            'allowedTransitions' => $this->allowedTransitionList($proposalSubmission->status),
            'samDocuments' => $this->samDocuments($proposalSubmission, $canUpdate),
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
                'editStyle' => $user->hasRole('Super Admin'),
            ],
        ]);
    }

    public function edit(Request $request, ProposalSubmission $proposalSubmission): Response|RedirectResponse
    {
        $this->authorize('view', $proposalSubmission);
        $user = $request->user();

        // Read-only users (not the owner / not a team member) can't open the
        // editor — send them back with a clear message naming the owner.
        if ($user->cannot('update', $proposalSubmission)) {
            return redirect()->route('proposals.show', $proposalSubmission)
                ->with('error', $this->notOwnerMessage($proposalSubmission));
        }

        $proposalSubmission->load('company:id,name', 'owner:id,name', 'teamMembers:id,proposal_submission_id,user_id');

        // The status is freely settable to any stage — the user picks whatever
        // they want and we record the change. Offer the full set of statuses.
        $statusOptions = collect(ProposalStatus::cases())
            ->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()])
            ->values();

        return Inertia::render('Proposals/Edit', [
            'proposal' => [
                'id' => $proposalSubmission->id,
                'proposal_number' => $proposalSubmission->proposal_number,
                'project_name' => $proposalSubmission->project_name,
                'proposal_type' => $proposalSubmission->proposal_type?->value ?? ProposalType::Proposal->value,
                'solicitation_number' => $proposalSubmission->solicitation_number,
                'company' => $proposalSubmission->company?->name ?? '',
                'proposal_value' => $proposalSubmission->proposal_value,
                'award_value' => $proposalSubmission->award_value,
                'currency' => $proposalSubmission->currency ?? Currency::DEFAULT,
                'status' => $proposalSubmission->status->value,
                'due_date' => $proposalSubmission->due_date?->format('Y-m-d'),
                'submission_date' => $proposalSubmission->submission_date?->format('Y-m-d'),
                'award_date' => $proposalSubmission->award_date?->format('Y-m-d'),
                'expected_award_date' => $proposalSubmission->expected_award_date?->format('Y-m-d'),
                'win_probability' => $proposalSubmission->win_probability,
                'owner_id' => $proposalSubmission->owner_id,
                'owner_name' => $proposalSubmission->owner?->name,
                'team_member_ids' => $proposalSubmission->teamMembers->pluck('user_id')->values(),
                'description' => $proposalSubmission->description,
                'scope_summary' => $proposalSubmission->scope_summary,
                'notes' => $proposalSubmission->notes,
                'submission_methods' => $proposalSubmission->submission_methods ?? [],
                'submission_portal_url' => $proposalSubmission->submission_portal_url,
            ],
            'users' => User::where('organization_id', $user->organization_id)->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'currencies' => Currency::options(),
            'statusOptions' => $statusOptions,
            'proposalTypes' => ProposalType::options(),
            'isAdmin' => $user->hasRole('Super Admin'),
        ]);
    }

    public function update(Request $request, ProposalSubmission $proposalSubmission): RedirectResponse
    {
        $this->authorize('update', $proposalSubmission);

        $validated = $request->validate([
            'project_name' => 'required|string|max:500',
            'proposal_type' => ['nullable', Rule::in(collect(ProposalType::cases())->map(fn ($t) => $t->value)->all())],
            'solicitation_number' => 'nullable|string|max:100',
            'company' => 'nullable|string|max:255',
            'proposal_value' => 'nullable|numeric|min:0',
            'award_value' => 'nullable|numeric|min:0',
            'currency' => ['nullable', Rule::in(Currency::codes())],
            'due_date' => 'nullable|date',
            'submission_date' => 'nullable|date',
            'award_date' => 'nullable|date',
            'expected_award_date' => 'nullable|date',
            'win_probability' => 'nullable|integer|min:0|max:100',
            'description' => 'nullable|string',
            'scope_summary' => 'nullable|string',
            'notes' => 'nullable|string',
            'owner_id' => 'nullable|exists:users,id',
            'team_member_ids' => 'nullable|array',
            'team_member_ids.*' => 'integer|exists:users,id',
            'submission_methods' => 'nullable|array',
            'submission_methods.*' => 'string|in:mail,email,portal',
            'submission_portal_url' => 'nullable|string|max:2048',
            'status' => ['nullable', Rule::in(collect(ProposalStatus::cases())->map(fn ($s) => $s->value)->all())],
        ]);

        $user = $request->user();
        $originalOwnerId = $proposalSubmission->owner_id;

        // Status changes go through the workflow (FSM), never a direct column write.
        $updates = collect($validated)->except(['company', 'currency', 'owner_id', 'team_member_ids', 'status', 'proposal_type', 'proposal_value'])->all();

        // Resolve the proposal type; RFIs carry no dollar value, so clear it.
        $type = ProposalType::tryFrom($validated['proposal_type'] ?? '')
            ?? $proposalSubmission->proposal_type
            ?? ProposalType::Proposal;
        $updates['proposal_type'] = $type->value;
        $updates['proposal_value'] = $type->hasValue() ? ($validated['proposal_value'] ?? null) : null;

        // Submission methods + portal link are edited together on the Create /
        // Edit forms. Only touch them when the form actually submitted them, so
        // editing other details (e.g. the inline detail editor, which omits these
        // fields) doesn't silently wipe them.
        if ($request->has('submission_methods')) {
            $updates['submission_methods'] = array_values(array_unique($validated['submission_methods'] ?? []));
            $updates['submission_portal_url'] = $this->normalizePortalUrl($validated);
        } else {
            unset($updates['submission_methods'], $updates['submission_portal_url']);
        }

        $updates['currency'] = Currency::normalize($validated['currency'] ?? $proposalSubmission->currency);
        $updates['company_id'] = $this->resolveCompanyId($validated['company'] ?? null, $user);
        $updates['updated_by'] = $user->id;

        // Collaborative ownership: any user who can edit the proposal (authorized
        // above) may reassign its owner, as long as the new owner is in the org.
        if (!empty($validated['owner_id']) && $this->userInOrg((int) $validated['owner_id'], $user)) {
            $updates['owner_id'] = (int) $validated['owner_id'];
            $updates['proposal_manager_id'] = (int) $validated['owner_id'];
        }

        $proposalSubmission->update($updates);

        $proposalSubmission->refresh();
        $this->syncTeamMembers($proposalSubmission, $validated['team_member_ids'] ?? [], $user, replace: true);
        $this->ensureOwnerOnTeam($proposalSubmission, $user);

        // Notify the new owner when ownership changed hands (admin reassignment).
        if ($proposalSubmission->owner_id && $proposalSubmission->owner_id !== $originalOwnerId
            && ($newOwner = User::find($proposalSubmission->owner_id))) {
            $this->notifier->proposalAssigned($proposalSubmission, $newOwner, $user);
        }

        // Apply a status change through the workflow if the form set one.
        $celebrate = null;
        $newStatus = $validated['status'] ?? null;
        if ($newStatus && $newStatus !== $proposalSubmission->status->value) {
            try {
                $this->workflow->transition($proposalSubmission, ProposalStatus::from($newStatus), $user, null, force: true);
                if ($newStatus === ProposalStatus::Submitted->value
                    && !app(\App\Services\Proposals\ProposalReadinessService::class)->missingSummary($proposalSubmission)) {
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
            'status' => ['required', Rule::in(collect(ProposalStatus::cases())->map(fn ($s) => $s->value)->all())],
            'notes' => 'nullable|string',
        ]);

        try {
            $this->workflow->transition($proposalSubmission, ProposalStatus::from($validated['status']), $request->user(), $validated['notes'] ?? null, force: true);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        $redirect = redirect()->route('proposals.show', $proposalSubmission)->with('success', 'Proposal status updated.');

        // Submitting a proposal is worth celebrating — but if it isn't submission-
        // ready (Phase 17), warn instead of celebrate so the gap is visible.
        if ($validated['status'] === ProposalStatus::Submitted->value) {
            if ($warning = app(\App\Services\Proposals\ProposalReadinessService::class)->missingSummary($proposalSubmission)) {
                $redirect->with('warning', $warning);
            } else {
                $redirect->with('celebrate', $proposalSubmission->proposal_number);
            }
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
            $this->workflow->transition($proposalSubmission, ProposalStatus::from($validated['status']), $request->user(), null, force: true);
        } catch (\Illuminate\Validation\ValidationException | \ValueError $e) {
            return back()->with('error', "That status change couldn't be applied.");
        }

        return back()->with('success', 'Status updated.');
    }

    /**
     * Log a client contact — resets the proposal's health clock to "green" and
     * clears the no-contact escalation ladder. Optionally records a note in the
     * proposal thread describing the contact.
     */
    public function logContact(Request $request, ProposalSubmission $proposalSubmission): RedirectResponse
    {
        $this->authorize('update', $proposalSubmission);

        $validated = $request->validate([
            'note' => 'nullable|string|max:2000',
            'contacted_at' => 'nullable|date',
        ]);

        $user = $request->user();
        $contactedAt = !empty($validated['contacted_at']) ? \Illuminate\Support\Carbon::parse($validated['contacted_at']) : now();

        $proposalSubmission->forceFill([
            'last_client_contact_at' => $contactedAt,
            'health_escalation_level' => 0,
        ])->save();

        \App\Models\FollowUp::create([
            'organization_id' => $proposalSubmission->organization_id,
            'created_by' => $user->id,
            'assigned_to' => $user->id,
            'proposal_submission_id' => $proposalSubmission->id,
            'type' => 'client_contact',
            'status' => 'sent',
            'subject' => 'Client contact logged',
            'message' => $validated['note'] ?: 'Client contact logged by ' . $user->name . '.',
            'scheduled_date' => $contactedAt->toDateString(),
            'sent_at' => $contactedAt,
        ]);

        return back()->with('success', 'Client contact logged — proposal health reset.');
    }

    /**
     * Shipments two-way link: attach an existing (unlinked) shipment to this
     * proposal. Gated on shipment access + proposal edit; org-scoped.
     */
    public function linkShipment(Request $request, ProposalSubmission $proposalSubmission): RedirectResponse
    {
        $this->authorize('update', $proposalSubmission);
        abort_unless($request->user()->can('access shipments'), 403);

        $validated = $request->validate(['ulid' => 'required|string']);

        $mailing = \App\Models\ProposalMailing::where('organization_id', $proposalSubmission->organization_id)
            ->where('ulid', $validated['ulid'])
            ->firstOrFail();

        $mailing->update([
            'proposal_submission_id' => $proposalSubmission->id,
            // Adopt the proposal's deadline when the shipment doesn't have one.
            'deadline' => $mailing->deadline ?? $proposalSubmission->due_date,
        ]);

        return back()->with('success', 'Shipment linked to this proposal.');
    }

    /** Detach the shipment from this proposal (the shipment itself is kept). */
    public function unlinkShipment(Request $request, ProposalSubmission $proposalSubmission): RedirectResponse
    {
        $this->authorize('update', $proposalSubmission);
        abort_unless($request->user()->can('access shipments'), 403);

        $proposalSubmission->mailing()->update(['proposal_submission_id' => null]);

        return back()->with('success', 'Shipment unlinked from this proposal.');
    }

    /**
     * Phase 18 — draft a follow-up email with the AI Brain and drop it into the
     * proposal thread as an editable draft the owner can refine and send.
     */
    public function draftFollowUp(Request $request, ProposalSubmission $proposalSubmission): RedirectResponse
    {
        $this->authorize('update', $proposalSubmission);

        $proposalSubmission->loadMissing('company:id,name', 'agency:id,name');

        $context = [
            'project_name' => $proposalSubmission->project_name,
            'proposal_number' => $proposalSubmission->proposal_number,
            'solicitation_number' => $proposalSubmission->solicitation_number,
            'status' => $proposalSubmission->status->label(),
            'client' => $proposalSubmission->company?->name ?? $proposalSubmission->agency?->name,
            'due_date' => $proposalSubmission->due_date?->toDateString(),
            'submission_date' => $proposalSubmission->submission_date?->toDateString(),
            'days_since_client_contact' => app(\App\Services\Proposals\ProposalHealthService::class)->daysSinceContact($proposalSubmission),
        ];

        try {
            $body = app(\App\Services\Ai\AiProviderInterface::class)->generateFollowUpEmail($context);
        } catch (\Throwable $e) {
            return back()->with('error', "Couldn't draft the follow-up email right now. Please try again.");
        }

        $user = $request->user();
        \App\Models\FollowUp::create([
            'organization_id' => $proposalSubmission->organization_id,
            'created_by' => $user->id,
            'assigned_to' => $user->id,
            'proposal_submission_id' => $proposalSubmission->id,
            'type' => 'email',
            'status' => 'scheduled',
            'subject' => 'Follow-up draft (AI)',
            'message' => trim($body),
            'scheduled_date' => now()->toDateString(),
            'is_automated' => true,
        ]);

        return back()->with('success', 'AI follow-up draft added to the proposal thread — review and send when ready.');
    }

    /**
     * Proposal Writer — draft a full proposal section (executive summary,
     * technical approach, etc.) with the active AI provider. Returns the text as
     * JSON for in-place display and also stores it as a reviewable AiAnalysis.
     */
    /** Step 1 of the writer: clarifying questions the writer needs before drafting. */
    public function draftSectionQuestions(Request $request, ProposalSubmission $proposalSubmission, ProposalWriterService $writer): JsonResponse
    {
        $this->authorize('update', $proposalSubmission);

        $validated = $request->validate([
            'section' => ['required', Rule::in(array_keys(ProposalWriterService::SECTIONS))],
        ]);

        try {
            $questions = $writer->questions($proposalSubmission, $validated['section']);
        } catch (\Throwable $e) {
            $questions = [];
        }

        return response()->json(['questions' => $questions]);
    }

    public function draftSection(Request $request, ProposalSubmission $proposalSubmission, ProposalWriterService $writer): JsonResponse
    {
        $this->authorize('update', $proposalSubmission);

        $validated = $request->validate([
            'section' => ['required', Rule::in(array_keys(ProposalWriterService::SECTIONS))],
            'answers' => 'nullable|array',
            'answers.*.question' => 'nullable|string|max:500',
            'answers.*.answer' => 'nullable|string|max:5000',
        ]);

        try {
            $text = $writer->draft($proposalSubmission, $validated['section'], $validated['answers'] ?? []);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Proposal section draft failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => "Couldn't draft that section right now. Please try again."], 422);
        }

        if (trim($text) === '') {
            return response()->json(['error' => 'No draft was generated. Please try again.'], 422);
        }

        AiAnalysis::create([
            'organization_id' => $proposalSubmission->organization_id,
            'created_by' => $request->user()->id,
            'subject_type' => ProposalSubmission::class,
            'subject_id' => $proposalSubmission->id,
            'analysis_type' => 'proposal_draft',
            'ai_provider' => app(AiProviderInterface::class)->getName(),
            'status' => 'needs_review',
            'context_data' => ['section' => $validated['section']],
            'output' => ['section' => $validated['section'], 'text' => $text],
        ]);

        return response()->json(['section' => $validated['section'], 'text' => $text]);
    }

    /** Persist a final (edited) proposal section for assembly into the export. */
    public function saveSection(Request $request, ProposalSubmission $proposalSubmission): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $proposalSubmission);

        $validated = $request->validate([
            'section_key' => ['required', Rule::in(array_keys(ProposalWriterService::SECTIONS))],
            'heading' => 'nullable|string|max:200',
            'content' => 'required|string',
        ]);

        $keys = array_keys(ProposalWriterService::SECTIONS);
        $section = $proposalSubmission->sections()->updateOrCreate(
            ['section_key' => $validated['section_key']],
            [
                'organization_id' => $proposalSubmission->organization_id,
                'heading' => $validated['heading'] ?: ProposalWriterService::SECTIONS[$validated['section_key']][0],
                'content' => $validated['content'],
                'sort_order' => array_search($validated['section_key'], $keys, true) ?: 0,
            ],
        );

        if ($request->expectsJson()) {
            return response()->json(['section' => [
                'id' => $section->id,
                'section_key' => $section->section_key,
                'heading' => $section->heading,
                'content' => $section->content,
            ]]);
        }

        return back()->with('success', 'Section saved to the proposal.');
    }

    public function deleteSection(Request $request, ProposalSubmission $proposalSubmission, ProposalSection $section): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $proposalSubmission);
        abort_unless($section->proposal_submission_id === $proposalSubmission->id, 404);

        $section->delete();

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('success', 'Section removed.');
    }

    /** Download the assembled proposal as Word (.docx) or PDF, styled by the org profile. */
    public function exportDocument(Request $request, ProposalSubmission $proposalSubmission, string $format, ProposalDocumentService $docs)
    {
        $this->authorize('view', $proposalSubmission);
        abort_unless(in_array($format, ['docx', 'pdf'], true), 404);

        $sections = $proposalSubmission->sections()->get();
        if ($sections->isEmpty()) {
            return back()->with('error', 'Add at least one drafted section before exporting.');
        }

        return $docs->download($proposalSubmission, $sections, $format);
    }

    /**
     * Phase 19 — save the loss-analysis fields for a lost proposal.
     */
    public function lossAnalysis(Request $request, ProposalSubmission $proposalSubmission): RedirectResponse
    {
        $this->authorize('update', $proposalSubmission);

        $validated = $request->validate([
            'loss_reason' => 'nullable|string',
            'loss_competitor' => 'nullable|string|max:255',
            'loss_competitor_price' => 'nullable|numeric|min:0',
            'debrief_requested' => 'boolean',
            'protest_recommended' => 'boolean',
            'lessons_learned' => 'nullable|string',
        ]);

        $proposalSubmission->update($validated);

        return back()->with('success', 'Loss analysis saved.');
    }

    /**
     * Phase 19 — generate an AI loss-assessment report from the proposal context
     * (why we likely lost, whether a debrief / protest is warranted).
     */
    public function generateLossAssessment(Request $request, ProposalSubmission $proposalSubmission): RedirectResponse
    {
        $this->authorize('update', $proposalSubmission);
        $proposalSubmission->loadMissing('company:id,name', 'agency:id,name');

        $context = [
            'project_name' => $proposalSubmission->project_name,
            'solicitation_number' => $proposalSubmission->solicitation_number,
            'client' => $proposalSubmission->company?->name ?? $proposalSubmission->agency?->name,
            'our_value' => $proposalSubmission->proposal_value,
            'currency' => $proposalSubmission->currency,
            'status' => $proposalSubmission->status->label(),
            'stated_loss_reason' => $proposalSubmission->loss_reason,
            'winning_competitor' => $proposalSubmission->loss_competitor,
            'winning_price' => $proposalSubmission->loss_competitor_price,
        ];

        $system = 'You are a U.S. government-contracting capture analyst. Write a concise, professional loss assessment in plain prose — no markdown headings, no preamble.';
        $prompt = "Write a short loss assessment for this lost/protested bid. Cover: the most likely reasons we lost, "
            . "whether requesting a debrief is advisable, whether a protest appears justified (and the bar for one), "
            . "and 1–2 lessons for next time. Be specific and realistic; do not invent facts not implied by the data.\n\n"
            . json_encode($context);

        try {
            $text = trim(app(\App\Services\Ai\AiProviderInterface::class)->complete($system, $prompt));
        } catch (\Throwable $e) {
            return back()->with('error', "Couldn't generate the loss assessment right now. Please try again.");
        }

        if ($text === '') {
            return back()->with('error', 'No assessment was generated. Add a loss reason and try again.');
        }

        $proposalSubmission->update(['loss_assessment' => $text]);

        return back()->with('success', 'AI loss assessment generated.');
    }

    public function destroy(Request $request, ProposalSubmission $proposalSubmission): RedirectResponse
    {
        $this->authorize('delete', $proposalSubmission);
        $proposalSubmission->delete();
        return redirect()->route('proposals.index')->with('success', 'Proposal deleted.');
    }

    /**
     * Solicitation documents for the proposal's linked SAM.gov opportunity, with
     * proxied preview/download URLs. Empty when there's no linked opportunity or
     * the source feed carries no resource links (e.g. SAM sync disabled).
     */
    private function samDocuments(ProposalSubmission $proposal, bool $canUpdate): array
    {
        $opportunity = $proposal->opportunity_id
            ? Opportunity::select('id', 'title', 'source', 'source_url', 'raw_source_data')->find($proposal->opportunity_id)
            : null;

        if (!$opportunity) {
            return ['linked' => false, 'documents' => [], 'can_extract' => false, 'notice_url' => null];
        }

        $documents = collect(app(\App\Services\BidSources\OpportunityDocumentService::class)->list($opportunity))
            ->map(fn ($d) => [
                'index' => $d['index'],
                'name' => $d['name'],
                'preview_url' => route('proposals.sam-documents.show', [$proposal, $d['index']]),
                'download_url' => route('proposals.sam-documents.show', [$proposal, $d['index']]) . '?dl=1',
                'extract_url' => route('proposals.sam-documents.extract', [$proposal, $d['index']]),
            ])
            ->values()
            ->all();

        return [
            'linked' => true,
            'opportunity_id' => $opportunity->id,
            'opportunity_title' => $opportunity->title,
            'documents' => $documents,
            'can_extract' => $canUpdate,
            'notice_url' => $opportunity->source_url,
        ];
    }

    /**
     * Countdown to the submission deadline for the detail page. The deadline is
     * the end of the due-date day in Pacific time, serialised with its offset so
     * the browser can tick down to the exact instant regardless of viewer
     * timezone. Returns null when there's no due date.
     *
     * @return array{deadline:string,due_date:string,submitted:bool,submission_date:?string}|null
     */
    private function countdown(ProposalSubmission $proposal): ?array
    {
        if (! $proposal->due_date) {
            return null;
        }

        $deadline = Carbon::parse($proposal->due_date->format('Y-m-d'), self::APP_TZ)->endOfDay();

        return [
            'deadline' => $deadline->toIso8601String(),
            'due_date' => $proposal->due_date->format('Y-m-d'),
            'submitted' => $proposal->submission_date !== null,
            'submission_date' => $proposal->submission_date?->format('Y-m-d'),
        ];
    }

    /**
     * Every status the detail-page dropdown offers. The status is freely
     * settable, so this is all statuses except the current one.
     *
     * @return array<int,array{value:string,label:string,color:string}>
     */
    /**
     * Apply the selectable date-range filter (due / submission / created) to a
     * proposals query. The picked days are read in Pacific time; due_date and
     * submission_date are plain date columns, created_at is a UTC datetime.
     */
    private function applyDateFilter($query, Request $request): void
    {
        $valid = fn ($v) => is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : null;
        $from = $valid($request->input('from'));
        $to = $valid($request->input('to'));
        if (!$from && !$to) {
            return;
        }

        $field = in_array($request->input('date_field'), ['due_date', 'submission_date', 'created_at'], true)
            ? $request->input('date_field') : 'due_date';

        if ($field === 'created_at') {
            if ($from) {
                $query->where('created_at', '>=', Carbon::parse($from, self::APP_TZ)->startOfDay()->utc());
            }
            if ($to) {
                $query->where('created_at', '<=', Carbon::parse($to, self::APP_TZ)->endOfDay()->utc());
            }
        } else {
            if ($from) {
                $query->whereDate($field, '>=', $from);
            }
            if ($to) {
                $query->whereDate($field, '<=', $to);
            }
        }
    }

    private function allowedTransitionList(ProposalStatus $current): array
    {
        return collect(ProposalStatus::cases())
            ->reject(fn ($s) => $s->value === $current->value)
            ->map(fn ($s) => ['value' => $s->value, 'label' => $s->label(), 'color' => $s->color()])
            ->values()
            ->all();
    }

    /**
     * Resolve the owner for a proposal. Any creator may assign ownership to
     * another user in their organization; otherwise it falls back to the
     * default (themselves).
     */
    private function resolveOwnerId(int|string|null $requested, User $actor, int $default): int
    {
        if ($requested && $this->userInOrg((int) $requested, $actor)) {
            return (int) $requested;
        }
        return $default;
    }

    private function userInOrg(int $userId, User $actor): bool
    {
        return User::where('id', $userId)->where('organization_id', $actor->organization_id)->exists();
    }

    /**
     * Friendly message shown when a non-owner tries to edit a proposal,
     * naming whoever currently owns it.
     */
    private function notOwnerMessage(ProposalSubmission $proposal): string
    {
        $owner = $proposal->owner?->name;
        return $owner
            ? "You're not the owner of this document — it's currently owned by {$owner}, so it's read-only for you."
            : "You're not the owner of this document, so it's read-only for you.";
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
     * The portal submission URL only applies when 'portal' is a chosen method.
     * Returns null otherwise (so a stale link never lingers), and prepends a
     * scheme when the user pasted a bare host so the link stays clickable.
     *
     * @param  array<string,mixed>  $validated
     */
    private function normalizePortalUrl(array $validated): ?string
    {
        $methods = (array) ($validated['submission_methods'] ?? []);
        if (! in_array('portal', $methods, true)) {
            return null;
        }

        $url = trim((string) ($validated['submission_portal_url'] ?? ''));
        if ($url === '') {
            return null;
        }

        if (! preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }

        return Str::limit($url, 2048, '');
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
