<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\Opportunity;
use App\Models\ProposalSubmission;
use App\Models\User;
use App\Enums\ProposalStatus;
use App\Services\Proposals\ProposalNumberService;
use App\Services\Proposals\ProposalWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProposalController extends Controller
{
    public function __construct(
        private readonly ProposalNumberService $numberService,
        private readonly ProposalWorkflowService $workflow,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', ProposalSubmission::class);

        $user = $request->user();
        $query = ProposalSubmission::forOrganization($user->organization_id)
            ->with(['owner:id,name', 'agency:id,name', 'proposalManager:id,name'])
            ->orderByDesc('created_at');

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

        return Inertia::render('Proposals/Index', [
            'proposals' => $query->paginate(25)->withQueryString(),
            'filters' => $request->only(['status', 'search']),
            'statuses' => collect(ProposalStatus::cases())->map(fn($s) => ['value' => $s->value, 'label' => $s->label(), 'color' => $s->color()]),
            'can' => ['create' => $user->can('create proposals')],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', ProposalSubmission::class);
        $user = $request->user();

        return Inertia::render('Proposals/Create', [
            'opportunities' => Opportunity::where('organization_id', $user->organization_id)->active()->orderBy('title')->get(['id', 'title', 'solicitation_number']),
            'agencies' => Agency::where('organization_id', $user->organization_id)->orderBy('name')->get(['id', 'name']),
            'users' => User::where('organization_id', $user->organization_id)->where('is_active', true)->get(['id', 'name']),
            'statuses' => collect(ProposalStatus::cases())->map(fn($s) => ['value' => $s->value, 'label' => $s->label()]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', ProposalSubmission::class);

        $validated = $request->validate([
            'project_name' => 'required|string|max:500',
            'opportunity_id' => 'nullable|exists:opportunities,id',
            'agency_id' => 'nullable|exists:agencies,id',
            'solicitation_number' => 'nullable|string|max:100',
            'proposal_value' => 'nullable|numeric|min:0',
            'due_date' => 'nullable|date',
            'description' => 'nullable|string',
            'proposal_manager_id' => 'nullable|exists:users,id',
        ]);

        $user = $request->user();

        $proposal = ProposalSubmission::create([
            ...$validated,
            'organization_id' => $user->organization_id,
            'created_by' => $user->id,
            'owner_id' => $user->id,
            'proposal_number' => $this->numberService->generate($user->organization_id),
            'status' => 'draft',
        ]);

        $proposal->statusHistory()->create([
            'changed_by' => $user->id,
            'from_status' => null,
            'to_status' => 'draft',
            'changed_at' => now(),
        ]);

        return redirect()->route('proposals.show', $proposal)->with('success', 'Proposal created.');
    }

    public function show(Request $request, ProposalSubmission $proposalSubmission): Response
    {
        $this->authorize('view', $proposalSubmission);

        $proposalSubmission->load([
            'opportunity:id,title,solicitation_number,due_date,estimated_value',
            'agency:id,name',
            'owner:id,name,email',
            'proposalManager:id,name,email',
            'statusHistory.changedBy:id,name',
            'teamMembers.user:id,name,email',
            'files',
            'notes.user:id,name',
            'complianceMatrices.items',
        ]);

        $user = $request->user();
        $allowedTransitions = $this->getAllowedTransitions($proposalSubmission->status);

        return Inertia::render('Proposals/Show', [
            'proposal' => $proposalSubmission,
            'allowedTransitions' => $allowedTransitions,
            'can' => [
                'update' => $user->can('update', $proposalSubmission),
                'delete' => $user->can('delete', $proposalSubmission),
                'submit' => $user->can('submit', $proposalSubmission),
                'approve' => $user->can('approve', $proposalSubmission),
                'viewPrivate' => $user->can('viewPrivateDetails', $proposalSubmission),
                'manageFiles' => $user->can('manage proposal files'),
            ],
        ]);
    }

    public function update(Request $request, ProposalSubmission $proposalSubmission): RedirectResponse
    {
        $this->authorize('update', $proposalSubmission);

        $validated = $request->validate([
            'project_name' => 'required|string|max:500',
            'agency_id' => 'nullable|exists:agencies,id',
            'proposal_value' => 'nullable|numeric|min:0',
            'due_date' => 'nullable|date',
            'description' => 'nullable|string',
            'notes' => 'nullable|string',
            'proposal_manager_id' => 'nullable|exists:users,id',
        ]);

        $proposalSubmission->update([...$validated, 'updated_by' => $request->user()->id]);

        return redirect()->route('proposals.show', $proposalSubmission)->with('success', 'Proposal updated.');
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

        return redirect()->route('proposals.show', $proposalSubmission)->with('success', 'Proposal status updated.');
    }

    public function destroy(Request $request, ProposalSubmission $proposalSubmission): RedirectResponse
    {
        $this->authorize('delete', $proposalSubmission);
        $proposalSubmission->delete();
        return redirect()->route('proposals.index')->with('success', 'Proposal deleted.');
    }

    private function getAllowedTransitions(ProposalStatus $currentStatus): array
    {
        $transitions = [
            'draft' => ['in_progress', 'cancelled'],
            'in_progress' => ['under_review', 'draft', 'cancelled'],
            'under_review' => ['in_progress', 'submitted', 'cancelled'],
            'submitted' => ['pending', 'clarification_requested', 'awarded', 'lost', 'cancelled'],
            'pending' => ['submitted', 'clarification_requested', 'awarded', 'lost', 'cancelled'],
            'clarification_requested' => ['submitted', 'lost', 'cancelled'],
            'negotiation' => ['awarded', 'lost', 'cancelled'],
            'awarded' => [],
            'lost' => [],
            'cancelled' => ['draft'],
        ];

        $allowedValues = $transitions[$currentStatus->value] ?? [];
        return collect(ProposalStatus::cases())
            ->filter(fn($s) => in_array($s->value, $allowedValues))
            ->map(fn($s) => ['value' => $s->value, 'label' => $s->label(), 'color' => $s->color()])
            ->values()
            ->toArray();
    }
}
