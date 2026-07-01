<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\Opportunity;
use App\Models\ProposalSubmission;
use App\Models\OpportunityUserState;
use App\Models\User;
use App\Enums\OpportunityAssignmentStage;
use App\Enums\OpportunityReaction;
use App\Enums\OpportunitySource;
use App\Enums\OpportunityStatus;
use App\Services\Proposals\ProposalNumberService;
use App\Services\BidSources\SamGov\SamGovImportService;
use App\Services\BidSources\BidPrime\FakeBidPrimeClient;
use App\Services\BidSources\OpportunityDeduplicationService;
use App\Services\BidSources\OpportunityDocumentService;
use App\Services\BidSources\OpportunityPipelineService;
use App\Services\Notifications\Notifier;
use App\Services\Opportunities\OpportunityTimelineService;
use App\Services\Opportunities\OpportunityWorkloadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class OpportunityController extends Controller
{
    public function index(Request $request, OpportunityPipelineService $pipeline): Response
    {
        $this->authorize('viewAny', Opportunity::class);

        $user = $request->user();

        // Keep the pipeline fresh on each visit (and on the 5-minute auto-refresh):
        // always drop expired opportunities, and pull from SAM.gov when the
        // throttle window has elapsed (runs after the response, never blocks).
        try {
            $pipeline->purgeExpired($user->organization_id);
            if ($pipeline->shouldSync($user->organization_id)) {
                app()->terminating(fn () => $pipeline->syncSamGov($user));
            }
        } catch (\Throwable) {
            // Pipeline maintenance must never break the page.
        }

        // Base query: organization scope + the shared filters (status, source,
        // NAICS, free-text search, keyword chips). The For You / Saved / All
        // tabs all build on this same filtered base.
        //
        // The pipeline only shows opportunities still worth acting on, so two
        // things are hidden everywhere: anything OVERDUE (its deadline has
        // passed — nothing to bid on) and anything already PICKED UP by the team
        // (a proposal was started from it, so it now lives under Applications).
        $base = Opportunity::forOrganization($user->organization_id)
            ->with(['agency', 'assignedTo:id,name', 'owner:id,name'])
            ->where(fn ($q) => $q->whereNull('due_date')->orWhereDate('due_date', '>=', now()->toDateString()))
            ->whereDoesntHave('proposals');

        if ($request->filled('status')) {
            $base->where('status', $request->status);
        }
        if ($request->filled('source')) {
            $base->where('source', $request->source);
        }
        if ($request->filled('naics')) {
            $base->where('naics_code', $request->naics);
        }
        if ($request->filled('search')) {
            $terms = preg_split('/[\s,]+/', (string) $request->search, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $this->applyKeywordMatch($base, $terms);
        }
        $selectedKeywords = array_values(array_filter(array_map(
            fn ($k) => trim((string) $k),
            (array) $request->input('keywords', [])
        )));
        if ($selectedKeywords) {
            $this->applyKeywordMatch($base, $selectedKeywords);
        }

        // The user's personal keywords power the "For You" feed; the watchlist
        // (manual stars) powers "Saved".
        $personalKeywords = array_values(array_filter(array_map('trim', (array) ($user->pipeline_keywords ?? []))));
        $savedIds = \Illuminate\Support\Facades\DB::table('opportunity_watchlists')
            ->where('user_id', $user->id)
            ->pluck('opportunity_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        // Tab counts (reflect the active filters, ignoring the tab itself).
        $counts = [
            'all' => (clone $base)->count(),
            'saved' => $savedIds ? (clone $base)->whereIn('id', $savedIds)->count() : 0,
            'foryou' => $personalKeywords ? (clone $base)->where(fn ($q) => $this->applyKeywordMatch($q, $personalKeywords))->count() : 0,
        ];

        // Apply the chosen tab.
        $view = in_array($request->input('view'), ['foryou', 'saved'], true) ? $request->input('view') : 'all';
        $query = clone $base;
        if ($view === 'saved') {
            $query->whereIn('id', $savedIds ?: [0]);
        } elseif ($view === 'foryou') {
            $personalKeywords
                ? $query->where(fn ($q) => $this->applyKeywordMatch($q, $personalKeywords))
                : $query->whereRaw('1 = 0'); // no keywords yet → empty feed
        }

        // Sorting (price, name, due date, agency, status, recency).
        $sortable = [
            'created_at' => 'created_at',
            'title' => 'title',
            'estimated_value' => 'estimated_value',
            'due_date' => 'due_date',
            'agency_name' => 'agency_name',
            'status' => 'status',
            'posted_date' => 'posted_date',
        ];
        $sort = $sortable[$request->input('sort')] ?? 'created_at';
        $direction = $request->input('direction') === 'asc' ? 'asc' : 'desc';
        if ($sort === 'due_date') {
            // Keep opportunities without a deadline at the bottom either way.
            $query->orderByRaw('due_date IS NULL')->orderBy('due_date', $direction);
        } else {
            $query->orderBy($sort, $direction);
        }

        $opportunities = $query->paginate(25)->withQueryString();

        return Inertia::render('Opportunities/Index', [
            'opportunities' => $opportunities,
            'filters' => [
                ...$request->only(['status', 'source', 'search', 'naics']),
                'keywords' => $selectedKeywords,
                'view' => $view,
                'sort' => array_search($sort, $sortable, true) ?: 'created_at',
                'direction' => $direction,
            ],
            'view' => $view,
            'counts' => $counts,
            'savedIds' => $savedIds,
            'keywordOptions' => config('pipeline.keywords', []),
            'personalKeywords' => $personalKeywords,
            'statuses' => collect(OpportunityStatus::cases())->map(fn($s) => ['value' => $s->value, 'label' => $s->label(), 'color' => $s->color()]),
            'sources' => collect(OpportunitySource::cases())->map(fn($s) => ['value' => $s->value, 'label' => $s->label()]),
            'can' => [
                'create' => $user->can('create opportunities'),
                'import' => $user->can('import opportunities'),
            ],
        ]);
    }

    /** Star/unstar an opportunity for the current user (the Saved tab). */
    public function toggleSave(Request $request, Opportunity $opportunity): RedirectResponse
    {
        $this->authorize('view', $opportunity);
        $user = $request->user();

        $table = \Illuminate\Support\Facades\DB::table('opportunity_watchlists')
            ->where('opportunity_id', $opportunity->id)
            ->where('user_id', $user->id);

        if ($table->exists()) {
            $table->delete();
        } else {
            \Illuminate\Support\Facades\DB::table('opportunity_watchlists')->insert([
                'opportunity_id' => $opportunity->id,
                'user_id' => $user->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return back(303);
    }

    /**
     * Add an OR group matching any of the given terms across the searchable
     * opportunity columns. No-op when the term list is empty.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Opportunity>  $query
     * @param  array<int,string>  $terms
     */
    private function applyKeywordMatch($query, array $terms): void
    {
        $terms = array_values(array_filter(array_map('trim', $terms)));
        if (!$terms) {
            return;
        }

        // matched_keywords is JSON text holding the sync keywords each SAM
        // notice was found under — covers full-text matches where the keyword
        // only appears in parts of the notice we don't store.
        $columns = ['title', 'description', 'scope', 'requirements_summary', 'agency_name', 'solicitation_number', 'naics_code', 'matched_keywords'];

        $query->where(function ($q) use ($terms, $columns) {
            foreach ($terms as $term) {
                foreach ($columns as $column) {
                    $q->orWhere($column, 'like', "%{$term}%");
                }
            }
        });
    }

    /** Add a private keyword to the current user's personal filter set. */
    public function storeKeyword(Request $request, OpportunityPipelineService $pipeline): RedirectResponse
    {
        $data = $request->validate(['keyword' => 'required|string|max:40']);
        $user = $request->user();
        $kw = trim($data['keyword']);

        $list = $user->pipeline_keywords ?? [];
        $taken = array_map('mb_strtolower', array_merge($list, config('pipeline.keywords', [])));
        if ($kw !== '' && !in_array(mb_strtolower($kw), $taken, true)) {
            $list[] = $kw;
            $user->update(['pipeline_keywords' => array_values($list)]);

            // Pull matching SAM.gov contracts right away (after the response is
            // sent) so the new keyword has results by the next refresh.
            if (config('integrations.sam_gov.sync_enabled', false)) {
                app()->terminating(function () use ($pipeline, $user, $kw) {
                    try {
                        $pipeline->importKeyword($user, $kw);
                    } catch (\Throwable $e) {
                        Log::warning('Keyword-targeted SAM pull failed', ['keyword' => $kw, 'error' => $e->getMessage()]);
                    }
                });
            }
        }

        return back(303)->with('success', "Keyword added — pulling matching SAM.gov contracts in the background.");
    }

    /** Remove one of the current user's private keywords. */
    public function destroyKeyword(Request $request): RedirectResponse
    {
        $data = $request->validate(['keyword' => 'required|string']);
        $user = $request->user();

        $list = array_values(array_filter(
            $user->pipeline_keywords ?? [],
            fn ($k) => mb_strtolower($k) !== mb_strtolower($data['keyword'])
        ));
        $user->update(['pipeline_keywords' => $list]);

        return back(303);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Opportunity::class);

        $user = $request->user();

        // Optional due-date prefill (e.g. when adding from the calendar).
        $due = $request->query('due');
        $due = is_string($due) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $due) ? $due : null;

        return Inertia::render('Opportunities/Create', [
            'agencies' => Agency::where('organization_id', $user->organization_id)->orderBy('name')->get(['id', 'name']),
            'users' => User::where('organization_id', $user->organization_id)->where('is_active', true)->get(['id', 'name']),
            'statuses' => collect(OpportunityStatus::cases())->map(fn($s) => ['value' => $s->value, 'label' => $s->label()]),
            'sources' => collect(OpportunitySource::cases())->map(fn($s) => ['value' => $s->value, 'label' => $s->label()]),
            'prefill' => ['due_date' => $due],
        ]);
    }

    public function store(Request $request, Notifier $notifier, OpportunityTimelineService $timeline): RedirectResponse
    {
        $this->authorize('create', Opportunity::class);

        $validated = $request->validate([
            'title' => 'required|string|max:500',
            'solicitation_number' => 'nullable|string|max:100',
            'source' => 'required|string',
            'status' => 'required|string',
            'agency_name' => 'nullable|string|max:255',
            'agency_id' => 'nullable|exists:agencies,id',
            'estimated_value' => 'nullable|numeric|min:0',
            'due_date' => 'nullable|date',
            'naics_code' => 'nullable|string|max:20',
            'description' => 'nullable|string',
            'notes' => 'nullable|string',
            'assigned_to' => 'nullable|exists:users,id',
        ]);

        $user = $request->user();

        // The creator owns it from the start (Assigned in the lifecycle); a
        // separate assignee may also be set.
        $opportunity = Opportunity::create([
            ...$validated,
            'organization_id' => $user->organization_id,
            'created_by' => $user->id,
            'owner_id' => $user->id,
            'assigned_to' => $validated['assigned_to'] ?? $user->id,
            'assignment_stage' => OpportunityAssignmentStage::Assigned->value,
            'assigned_at' => now(),
            'last_activity_at' => now(),
        ]);

        $timeline->record($opportunity, OpportunityTimelineService::DISCOVERED, 'Opportunity added by ' . $user->name . '.', $user, touchActivity: false);

        $notifier->opportunityCreated($opportunity, $user);

        return redirect()->route('opportunities.index')->with('success', 'Opportunity created successfully.');
    }

    public function show(Request $request, Opportunity $opportunity, \App\Services\Opportunities\OpportunityHealthService $health, \App\Services\BidSources\SamGov\SamLinkResolver $samLinks): Response
    {
        $this->authorize('view', $opportunity);

        $this->ensureSamLink($opportunity, $samLinks);

        $opportunity->load([
            'agency', 'company.contacts:id,company_id,first_name,last_name,email,phone,title',
            'assignedTo:id,name,email', 'owner:id,name,email',
            'proposals:id,opportunity_id,proposal_number,project_name,status,due_date,proposal_value',
            'amendments',
            'competitors',
            'partners.company:id,name',
            'goNoGoReviews.reviewedBy:id,name',
        ]);

        $contacts = ($opportunity->company?->contacts ?? collect())->map(fn ($c) => [
            'id' => $c->id,
            'name' => trim("{$c->first_name} {$c->last_name}"),
            'title' => $c->title,
            'email' => $c->email,
            'phone' => $c->phone,
        ])->values();

        $user = $request->user();
        $canManage = $this->canManageAssignments($user);
        $isOwner = $opportunity->owner_id === $user->id;

        $myState = OpportunityUserState::where('opportunity_id', $opportunity->id)
            ->where('user_id', $user->id)
            ->first();

        // Managers see the AI-recommended owners (primary/secondary) for routing.
        $recommendedOwners = $canManage
            ? OpportunityUserState::where('opportunity_id', $opportunity->id)
                ->where('is_recommended', true)
                ->with('user:id,name')
                ->orderByDesc('match_score')
                ->get()
                ->map(fn (OpportunityUserState $s) => [
                    'user' => $s->user?->name,
                    'score' => $s->match_score !== null ? (float) $s->match_score : null,
                    'role' => $s->recommended_role,
                    'reasons' => $s->match_reasons,
                ])->values()
            : [];

        $opportunity->append('sam_url');

        // Pull the solicitation files in on first view if they weren't captured at
        // import; the status tells us whether an empty list is a confirmed "no
        // attachments" or just not-fetched-yet (so the UI doesn't cry wolf).
        $docService = app(OpportunityDocumentService::class);
        $docStatus = $docService->ensure($opportunity);
        $samDocuments = $this->samDocuments($opportunity, $docService);

        return Inertia::render('Opportunities/Show', [
            'opportunity' => $opportunity,
            'contacts' => $contacts,
            'samDocuments' => $samDocuments,
            'samDocumentsPending' => $samDocuments === [] && ! in_array($docStatus, ['none', 'none_cached'], true),
            'timeline' => $this->timeline($opportunity),
            'lifecycle' => [
                'stage' => $opportunity->assignment_stage?->value,
                'stage_label' => $opportunity->assignment_stage?->label(),
                'stage_color' => $opportunity->assignment_stage?->color(),
                'owner' => $opportunity->owner?->only(['id', 'name', 'email']),
                'assigned_to' => $opportunity->assignedTo?->only(['id', 'name', 'email']),
                'ownership_locked' => (bool) $opportunity->ownership_locked,
                'assigned_at' => $opportunity->assigned_at?->toIso8601String(),
                'last_activity_at' => $opportunity->last_activity_at?->toIso8601String(),
                'days_since_activity' => $opportunity->days_since_activity,
                'days_until_deadline' => $opportunity->days_until_deadline,
                'my_reaction' => $myState?->reaction?->value,
                'my_match' => $myState && $myState->match_score !== null
                    ? ['score' => (float) $myState->match_score, 'reasons' => $myState->match_reasons, 'role' => $myState->recommended_role]
                    : null,
                'is_owner' => $isOwner,
            ],
            'recommendedOwners' => $recommendedOwners,
            'health' => $health->score($opportunity),
            'reactionOptions' => OpportunityReaction::options(),
            'stageOptions' => array_map(
                fn (OpportunityAssignmentStage $s) => ['value' => $s->value, 'label' => $s->label(), 'color' => $s->color()],
                OpportunityAssignmentStage::workStages(),
            ),
            'assignableUsers' => $canManage
                ? User::where('organization_id', $user->organization_id)->where('is_active', true)->orderBy('name')->get(['id', 'name'])
                : [],
            'can' => [
                'update' => $user->can('update', $opportunity),
                'delete' => $user->can('delete', $opportunity),
                'makeGoNoGo' => $user->can('makeGoNoGoDecision', $opportunity),
                'pursue' => $user->can('create proposals'),
                'claim' => ! $opportunity->ownership_locked || $isOwner || $canManage,
                'assign' => $canManage,
                'changeStage' => $isOwner || $canManage,
            ],
        ]);
    }

    /**
     * The opportunity's immutable timeline (newest first). A synthetic
     * "discovered" entry is prepended from created_at when no explicit
     * discovery event was recorded (e.g. SAM.gov-imported opportunities).
     *
     * @return array<int,array<string,mixed>>
     */
    private function timeline(Opportunity $opportunity): array
    {
        $events = $opportunity->events()->with('user:id,name')->limit(200)->get()->map(fn ($e) => [
            'id' => $e->id,
            'type' => $e->type,
            'description' => $e->description,
            'meta' => $e->meta,
            'user' => $e->user?->name,
            'at' => $e->created_at?->toIso8601String(),
        ])->values()->all();

        $hasDiscovery = collect($events)->contains(fn ($e) => $e['type'] === OpportunityTimelineService::DISCOVERED);
        if (! $hasDiscovery) {
            $events[] = [
                'id' => 0,
                'type' => OpportunityTimelineService::DISCOVERED,
                'description' => 'Discovered via ' . ($opportunity->source?->label() ?? 'manual entry') . '.',
                'meta' => null,
                'user' => null,
                'at' => $opportunity->created_at?->toIso8601String(),
            ];
        }

        return $events;
    }

    /**
     * Solicitation documents this opportunity carries from SAM.gov, with proxied
     * preview/download URLs. Empty when the source feed has no resource links
     * (e.g. SAM sync disabled) — the UI shows an empty state.
     *
     * Deep-link to the exact SAM.gov notice. Older SAM opportunities were
     * imported without a notice id, so resolve it from the solicitation number
     * on first view and persist it (so `sam_url` links straight to the notice).
     * Best-effort: a cached miss avoids re-hitting SAM for notices we can't match,
     * and any failure simply leaves the search-link fallback in place.
     */
    private function ensureSamLink(Opportunity $opportunity, \App\Services\BidSources\SamGov\SamLinkResolver $samLinks): void
    {
        if ($opportunity->source?->value !== 'sam_gov'
            || $opportunity->external_id
            || ! $opportunity->solicitation_number
            || \Illuminate\Support\Facades\Cache::has("sam_link_tried:{$opportunity->id}")) {
            return;
        }

        try {
            $noticeId = $samLinks->noticeIdForSolicitation((string) $opportunity->solicitation_number, timeout: 10);
            if ($noticeId) {
                $opportunity->forceFill([
                    'external_id' => $noticeId,
                    'source_url' => "https://sam.gov/workspace/contract/opp/{$noticeId}/view",
                ])->saveQuietly();
            } else {
                \Illuminate\Support\Facades\Cache::put("sam_link_tried:{$opportunity->id}", true, now()->addDay());
            }
        } catch (\Throwable $e) {
            // Non-fatal — fall back to the search link and try again on a later view.
        }
    }

    /**
     * @return array<int,array{index:int,name:string,preview_url:string,download_url:string}>
     */
    private function samDocuments(Opportunity $opportunity, OpportunityDocumentService $docs): array
    {
        return collect($docs->list($opportunity))
            ->map(fn ($d) => [
                'index' => $d['index'],
                'name' => $d['name'],
                'preview_url' => route('opportunities.documents.show', [$opportunity, $d['index']]),
                'download_url' => route('opportunities.documents.show', [$opportunity, $d['index']]) . '?dl=1',
            ])
            ->values()
            ->all();
    }

    /**
     * Proxy a solicitation document from the opportunity's SAM.gov record. The
     * file lives on SAM's servers — streamed through so the API key stays
     * server-side. Append ?dl=1 to download instead of preview inline.
     */
    public function document(Request $request, Opportunity $opportunity, int $index, OpportunityDocumentService $docs): mixed
    {
        $this->authorize('view', $opportunity);

        $docs->ensure($opportunity);
        $url = $docs->urlAt($opportunity, $index);
        abort_if($url === null, 404, 'Document not found.');

        $fetched = $docs->fetch($url);
        abort_if($fetched === null, 502, 'Could not retrieve the document from SAM.gov.');

        $disposition = $request->boolean('dl') ? 'attachment' : 'inline';

        return response($fetched['body'], 200, [
            'Content-Type' => $fetched['mime'],
            'Content-Disposition' => $disposition . '; filename="' . addslashes($fetched['filename']) . '"',
            'X-Frame-Options' => 'SAMEORIGIN',
        ]);
    }

    /**
     * Start an application (proposal) from an opportunity. Links the new draft
     * proposal to the opportunity so document prep can begin.
     */
    public function pursue(Request $request, Opportunity $opportunity, ProposalNumberService $numberService, Notifier $notifier, OpportunityTimelineService $timeline): RedirectResponse
    {
        $this->authorize('view', $opportunity);
        $this->authorize('create', ProposalSubmission::class);

        $user = $request->user();

        $existing = ProposalSubmission::where('organization_id', $user->organization_id)
            ->where('opportunity_id', $opportunity->id)->first();
        if ($existing) {
            return redirect()->route('proposals.show', $existing)->with('success', 'Application already started for this opportunity.');
        }

        $proposal = ProposalSubmission::create([
            'organization_id' => $user->organization_id,
            'created_by' => $user->id,
            'owner_id' => $user->id,
            'proposal_manager_id' => $user->id,
            'opportunity_id' => $opportunity->id,
            'company_id' => $opportunity->company_id,
            'agency_id' => $opportunity->agency_id,
            'proposal_number' => $numberService->generate($user->organization_id),
            'project_name' => $opportunity->title,
            'solicitation_number' => $opportunity->solicitation_number,
            'proposal_value' => $opportunity->estimated_value,
            'due_date' => $opportunity->due_date,
            'status' => 'in_progress',
        ]);

        $proposal->statusHistory()->create([
            'changed_by' => $user->id,
            'from_status' => null,
            'to_status' => 'in_progress',
            'changed_at' => now(),
        ]);

        // Advance the opportunity's assignment lifecycle — pursuing locks
        // ownership to whoever started the proposal and moves it to drafting.
        $opportunity->forceFill([
            'assignment_stage' => OpportunityAssignmentStage::ProposalDrafting->value,
            'owner_id' => $opportunity->owner_id ?? $user->id,
            'assigned_to' => $opportunity->assigned_to ?? $user->id,
            'ownership_locked' => true,
            'ownership_locked_at' => $opportunity->ownership_locked_at ?? now(),
            'assigned_at' => $opportunity->assigned_at ?? now(),
            'accepted_at' => $opportunity->accepted_at ?? now(),
        ])->save();

        $timeline->record($opportunity, OpportunityTimelineService::STAGE_CHANGED, $user->name . ' started a proposal (Proposal Drafting).', $user, ['proposal_id' => $proposal->id]);

        $notifier->proposalCreated($proposal, $user);

        return redirect()->route('proposals.show', $proposal)
            ->with('success', 'Application started — upload your documents and QuakeAI will help prep the details.');
    }

    public function edit(Request $request, Opportunity $opportunity): Response
    {
        $this->authorize('update', $opportunity);

        $user = $request->user();

        return Inertia::render('Opportunities/Edit', [
            'opportunity' => $opportunity,
            'agencies' => Agency::where('organization_id', $user->organization_id)->orderBy('name')->get(['id', 'name']),
            'users' => User::where('organization_id', $user->organization_id)->where('is_active', true)->get(['id', 'name']),
            'statuses' => collect(OpportunityStatus::cases())->map(fn($s) => ['value' => $s->value, 'label' => $s->label()]),
        ]);
    }

    public function update(Request $request, Opportunity $opportunity): RedirectResponse
    {
        $this->authorize('update', $opportunity);

        $validated = $request->validate([
            'title' => 'required|string|max:500',
            'solicitation_number' => 'nullable|string|max:100',
            'status' => 'required|string',
            'agency_name' => 'nullable|string|max:255',
            'agency_id' => 'nullable|exists:agencies,id',
            'estimated_value' => 'nullable|numeric|min:0',
            'due_date' => 'nullable|date',
            'naics_code' => 'nullable|string|max:20',
            'description' => 'nullable|string',
            'notes' => 'nullable|string',
            'assigned_to' => 'nullable|exists:users,id',
        ]);

        $opportunity->update([...$validated, 'updated_by' => $request->user()->id]);

        return redirect()->route('opportunities.show', $opportunity)->with('success', 'Opportunity updated.');
    }

    /**
     * Claim (and lock) ownership of an opportunity — moves it to "In Progress".
     * Once locked, other users may view but cannot claim it; only the owner or
     * an assignment manager (admin/CEO) can reassign.
     */
    public function claim(Request $request, Opportunity $opportunity, OpportunityTimelineService $timeline, OpportunityWorkloadService $workload, Notifier $notifier): RedirectResponse
    {
        $this->authorize('view', $opportunity);
        $user = $request->user();

        if (! $this->canClaim($opportunity, $user)) {
            return back(303)->with('error', 'This opportunity is owned by ' . ($opportunity->owner?->name ?? 'another user') . '. Ask an admin to reassign it.');
        }

        $this->lockOwnership($opportunity, $user, $timeline);
        $this->setReaction($opportunity, $user, OpportunityReaction::InProgress, $timeline, recordEvent: false);

        $notifier->opportunityClaimed($opportunity->fresh(['owner']), $user);
        $workload->recompute($user);

        return back(303)->with('success', 'You now own this opportunity.');
    }

    /**
     * Record the current user's reaction to a recommended opportunity. Choosing
     * "In Progress" claims and locks ownership (per the ownership rules).
     */
    public function react(Request $request, Opportunity $opportunity, OpportunityTimelineService $timeline, OpportunityWorkloadService $workload, Notifier $notifier): RedirectResponse
    {
        $this->authorize('view', $opportunity);
        $user = $request->user();

        $data = $request->validate([
            'reaction' => ['required', Rule::in(array_map(fn (OpportunityReaction $r) => $r->value, OpportunityReaction::cases()))],
        ]);
        $reaction = OpportunityReaction::from($data['reaction']);

        if ($reaction === OpportunityReaction::InProgress) {
            if (! $this->canClaim($opportunity, $user)) {
                return back(303)->with('error', 'This opportunity is owned by ' . ($opportunity->owner?->name ?? 'another user') . '. Ask an admin to reassign it.');
            }
            $this->lockOwnership($opportunity, $user, $timeline);
            $this->setReaction($opportunity, $user, $reaction, $timeline, recordEvent: false);
            $notifier->opportunityClaimed($opportunity->fresh(['owner']), $user);
            $workload->recompute($user);

            return back(303)->with('success', 'You now own this opportunity.');
        }

        $this->setReaction($opportunity, $user, $reaction, $timeline);

        return back(303);
    }

    /** Assign or reassign an opportunity to a user (assignment managers only). */
    public function assign(Request $request, Opportunity $opportunity, OpportunityTimelineService $timeline, OpportunityWorkloadService $workload, Notifier $notifier): RedirectResponse
    {
        $this->authorize('update', $opportunity);
        $user = $request->user();
        abort_unless($this->canManageAssignments($user), 403, 'You do not have permission to assign opportunities.');

        $data = $request->validate([
            'user_id' => ['required', Rule::exists('users', 'id')->where('organization_id', $user->organization_id)],
        ]);
        $target = User::findOrFail($data['user_id']);
        $previousOwnerId = $opportunity->owner_id;

        $opportunity->forceFill([
            'owner_id' => $target->id,
            'assigned_to' => $target->id,
            'assignment_stage' => OpportunityAssignmentStage::Assigned->value,
            'assigned_at' => now(),
            'accepted_at' => null,
            'ownership_locked' => false,
            'ownership_locked_at' => null,
            'assignment_escalation_level' => 0,
            'updated_by' => $user->id,
        ])->save();

        $timeline->record($opportunity, OpportunityTimelineService::REASSIGNED, $user->name . ' assigned this to ' . $target->name . '.', $user, ['to_user_id' => $target->id]);
        $notifier->opportunityAssigned($opportunity, $target, $user);

        $workload->recompute($target);
        if ($previousOwnerId && $previousOwnerId !== $target->id && ($prev = User::find($previousOwnerId))) {
            $workload->recompute($prev);
        }

        return back(303)->with('success', 'Opportunity assigned to ' . $target->name . '.');
    }

    /** Move an owned opportunity along its work stages (owner or manager). */
    public function advanceStage(Request $request, Opportunity $opportunity, OpportunityTimelineService $timeline, OpportunityWorkloadService $workload): RedirectResponse
    {
        $this->authorize('view', $opportunity);
        $user = $request->user();
        abort_unless($opportunity->owner_id === $user->id || $this->canManageAssignments($user), 403, 'Only the owner can change the stage.');

        $data = $request->validate([
            'stage' => ['required', Rule::in(array_map(fn (OpportunityAssignmentStage $s) => $s->value, OpportunityAssignmentStage::workStages()))],
        ]);
        $from = $opportunity->assignment_stage;
        $to = OpportunityAssignmentStage::from($data['stage']);
        if ($from === $to) {
            return back(303);
        }

        $opportunity->forceFill(['assignment_stage' => $to->value, 'updated_by' => $user->id])->save();
        $timeline->record($opportunity, OpportunityTimelineService::STAGE_CHANGED, $user->name . ' moved the stage to "' . $to->label() . '".', $user, ['from' => $from?->value, 'to' => $to->value]);

        if ($to->isClosed() && $opportunity->owner_id && ($owner = User::find($opportunity->owner_id))) {
            $workload->recompute($owner);
        }

        return back(303)->with('success', 'Stage updated to ' . $to->label() . '.');
    }

    /** Relinquish ownership — owner or manager — returning it to Unassigned. */
    public function release(Request $request, Opportunity $opportunity, OpportunityTimelineService $timeline, OpportunityWorkloadService $workload): RedirectResponse
    {
        $this->authorize('view', $opportunity);
        $user = $request->user();
        abort_unless($opportunity->owner_id === $user->id || $this->canManageAssignments($user), 403, 'Only the owner or an admin can release this opportunity.');

        $previousOwnerId = $opportunity->owner_id;
        $opportunity->forceFill([
            'owner_id' => null,
            'assigned_to' => null,
            'assignment_stage' => OpportunityAssignmentStage::Unassigned->value,
            'ownership_locked' => false,
            'ownership_locked_at' => null,
            'updated_by' => $user->id,
        ])->save();

        $timeline->record($opportunity, OpportunityTimelineService::UNLOCKED, $user->name . ' released ownership — opportunity is unassigned.', $user);

        if ($previousOwnerId && ($prev = User::find($previousOwnerId))) {
            $workload->recompute($prev);
        }

        return back(303)->with('success', 'Ownership released. The opportunity is now unassigned.');
    }

    /** Can this user claim the opportunity right now? */
    private function canClaim(Opportunity $opportunity, User $user): bool
    {
        return ! $opportunity->ownership_locked
            || $opportunity->owner_id === $user->id
            || $this->canManageAssignments($user);
    }

    private function canManageAssignments(User $user): bool
    {
        return $user->can('assign opportunities');
    }

    /** Lock ownership to a user and move the lifecycle to In Progress. */
    private function lockOwnership(Opportunity $opportunity, User $user, OpportunityTimelineService $timeline): void
    {
        $opportunity->forceFill([
            'owner_id' => $user->id,
            'assigned_to' => $opportunity->assigned_to ?? $user->id,
            'assignment_stage' => OpportunityAssignmentStage::InProgress->value,
            'ownership_locked' => true,
            'ownership_locked_at' => now(),
            'assigned_at' => $opportunity->assigned_at ?? now(),
            'accepted_at' => $opportunity->accepted_at ?? now(),
            'assignment_escalation_level' => 0,
        ])->save();

        $timeline->record($opportunity, OpportunityTimelineService::CLAIMED, $user->name . ' claimed ownership (In Progress).', $user);
    }

    /** Upsert the user's reaction state for an opportunity. */
    private function setReaction(Opportunity $opportunity, User $user, ?OpportunityReaction $reaction, OpportunityTimelineService $timeline, bool $recordEvent = true): OpportunityUserState
    {
        $state = OpportunityUserState::firstOrNew([
            'opportunity_id' => $opportunity->id,
            'user_id' => $user->id,
        ]);
        $state->organization_id = $opportunity->organization_id;
        $state->reaction = $reaction;
        $state->reacted_at = now();
        $state->save();

        if ($recordEvent && $reaction) {
            $timeline->record($opportunity, OpportunityTimelineService::REACTION, $user->name . ' marked it "' . $reaction->label() . '".', $user);
        }

        return $state;
    }

    public function destroy(Request $request, Opportunity $opportunity): RedirectResponse
    {
        $this->authorize('delete', $opportunity);
        $opportunity->delete();
        return redirect()->route('opportunities.index')->with('success', 'Opportunity deleted.');
    }

    public function importSamGov(Request $request, SamGovImportService $importService): RedirectResponse
    {
        $this->authorize('import', Opportunity::class);

        $filters = $request->validate([
            'naics_codes' => 'nullable|array',
            'keywords' => 'nullable|string',
        ]);

        $stats = $importService->import($request->user()->organization, $filters, $request->user());

        return redirect()->route('opportunities.index')->with('success',
            "SAM.gov import complete: {$stats['imported']} new, {$stats['updated']} updated, {$stats['errors']} errors."
        );
    }
}
