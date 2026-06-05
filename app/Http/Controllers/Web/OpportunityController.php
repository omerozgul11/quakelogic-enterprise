<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\Opportunity;
use App\Models\User;
use App\Enums\OpportunitySource;
use App\Enums\OpportunityStatus;
use App\Services\BidSources\SamGov\SamGovImportService;
use App\Services\BidSources\BidPrime\FakeBidPrimeClient;
use App\Services\BidSources\OpportunityDeduplicationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class OpportunityController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Opportunity::class);

        $user = $request->user();
        $query = Opportunity::forOrganization($user->organization_id)
            ->with(['agency', 'assignedTo:id,name', 'owner:id,name'])
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('source')) {
            $query->where('source', $request->source);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(fn($q) => $q
                ->where('title', 'like', "%{$search}%")
                ->orWhere('solicitation_number', 'like', "%{$search}%")
                ->orWhere('agency_name', 'like', "%{$search}%")
            );
        }
        if ($request->filled('naics')) {
            $query->where('naics_code', $request->naics);
        }

        $opportunities = $query->paginate(25)->withQueryString();

        return Inertia::render('Opportunities/Index', [
            'opportunities' => $opportunities,
            'filters' => $request->only(['status', 'source', 'search', 'naics']),
            'statuses' => collect(OpportunityStatus::cases())->map(fn($s) => ['value' => $s->value, 'label' => $s->label(), 'color' => $s->color()]),
            'sources' => collect(OpportunitySource::cases())->map(fn($s) => ['value' => $s->value, 'label' => $s->label()]),
            'can' => [
                'create' => $user->can('create opportunities'),
                'import' => $user->can('import opportunities'),
            ],
        ]);
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

    public function store(Request $request): RedirectResponse
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

        Opportunity::create([
            ...$validated,
            'organization_id' => $user->organization_id,
            'created_by' => $user->id,
            'owner_id' => $user->id,
        ]);

        return redirect()->route('opportunities.index')->with('success', 'Opportunity created successfully.');
    }

    public function show(Request $request, Opportunity $opportunity): Response
    {
        $this->authorize('view', $opportunity);

        $opportunity->load([
            'agency', 'company', 'assignedTo:id,name,email', 'owner:id,name,email',
            'capturePlan.captureManager:id,name',
            'proposals:id,proposal_number,project_name,status,due_date,proposal_value',
            'amendments',
            'competitors',
            'partners.company:id,name',
            'goNoGoReviews.reviewedBy:id,name',
        ]);

        return Inertia::render('Opportunities/Show', [
            'opportunity' => $opportunity,
            'can' => [
                'update' => $request->user()->can('update', $opportunity),
                'delete' => $request->user()->can('delete', $opportunity),
                'makeGoNoGo' => $request->user()->can('makeGoNoGoDecision', $opportunity),
            ],
        ]);
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
