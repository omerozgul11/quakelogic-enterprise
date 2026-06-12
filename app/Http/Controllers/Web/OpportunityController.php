<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\Opportunity;
use App\Models\ProposalSubmission;
use App\Models\User;
use App\Enums\OpportunitySource;
use App\Enums\OpportunityStatus;
use App\Services\Proposals\ProposalNumberService;
use App\Services\BidSources\SamGov\SamGovImportService;
use App\Services\BidSources\BidPrime\FakeBidPrimeClient;
use App\Services\BidSources\OpportunityDeduplicationService;
use App\Services\BidSources\OpportunityDocumentService;
use App\Services\BidSources\OpportunityPipelineService;
use App\Services\Notifications\Notifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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
        $base = Opportunity::forOrganization($user->organization_id)
            ->with(['agency', 'assignedTo:id,name', 'owner:id,name']);

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

        return Inertia::render('Opportunities/Create', [
            'agencies' => Agency::where('organization_id', $user->organization_id)->orderBy('name')->get(['id', 'name']),
            'users' => User::where('organization_id', $user->organization_id)->where('is_active', true)->get(['id', 'name']),
            'statuses' => collect(OpportunityStatus::cases())->map(fn($s) => ['value' => $s->value, 'label' => $s->label()]),
            'sources' => collect(OpportunitySource::cases())->map(fn($s) => ['value' => $s->value, 'label' => $s->label()]),
        ]);
    }

    public function store(Request $request, Notifier $notifier): RedirectResponse
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

        $opportunity = Opportunity::create([
            ...$validated,
            'organization_id' => $user->organization_id,
            'created_by' => $user->id,
            'owner_id' => $user->id,
        ]);

        $notifier->opportunityCreated($opportunity, $user);

        return redirect()->route('opportunities.index')->with('success', 'Opportunity created successfully.');
    }

    public function show(Request $request, Opportunity $opportunity): Response
    {
        $this->authorize('view', $opportunity);

        $opportunity->load([
            'agency', 'company.contacts:id,company_id,first_name,last_name,email,phone,title',
            'assignedTo:id,name,email', 'owner:id,name,email',
            'proposals:id,proposal_number,project_name,status,due_date,proposal_value',
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

        return Inertia::render('Opportunities/Show', [
            'opportunity' => $opportunity,
            'contacts' => $contacts,
            'samDocuments' => $this->samDocuments($opportunity),
            'can' => [
                'update' => $request->user()->can('update', $opportunity),
                'delete' => $request->user()->can('delete', $opportunity),
                'makeGoNoGo' => $request->user()->can('makeGoNoGoDecision', $opportunity),
                'pursue' => $request->user()->can('create proposals'),
            ],
        ]);
    }

    /**
     * Solicitation documents this opportunity carries from SAM.gov, with proxied
     * preview/download URLs. Empty when the source feed has no resource links
     * (e.g. SAM sync disabled) — the UI shows an empty state.
     *
     * @return array<int,array{index:int,name:string,preview_url:string,download_url:string}>
     */
    private function samDocuments(Opportunity $opportunity): array
    {
        return collect(app(OpportunityDocumentService::class)->list($opportunity))
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
    public function pursue(Request $request, Opportunity $opportunity, ProposalNumberService $numberService, Notifier $notifier): RedirectResponse
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
