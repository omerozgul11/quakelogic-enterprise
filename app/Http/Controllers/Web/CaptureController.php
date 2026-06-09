<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\CapturePlan;
use App\Models\Opportunity;
use App\Models\User;
use App\Enums\CaptureStage;
use App\Services\Capture\CaptureWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CaptureController extends Controller
{
    public function __construct(private readonly CaptureWorkflowService $workflow) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', CapturePlan::class);

        $user = $request->user();
        $plans = CapturePlan::where('organization_id', $user->organization_id)
            ->with(['opportunity:id,title,solicitation_number,estimated_value,due_date', 'captureManager:id,name'])
            ->when($request->stage, fn($q, $stage) => $q->where('stage', $stage))
            ->orderByDesc('updated_at')
            ->paginate(20)->withQueryString();

        return Inertia::render('Capture/Index', [
            'capturePlans' => $plans,
            'stages' => collect(CaptureStage::cases())->map(fn($s) => ['value' => $s->value, 'label' => $s->label(), 'color' => $s->color(), 'order' => $s->order()]),
            'filters' => $request->only(['stage']),
            'can' => ['manage' => $user->can('manage capture plans')],
        ]);
    }

    public function show(Request $request, CapturePlan $capturePlan): Response
    {
        $this->authorize('view', $capturePlan);

        $capturePlan->load([
            'opportunity',
            'captureManager:id,name',
            'stageHistory.changedBy:id,name',
            'risks',
            'tasks.assignedTo:id,name',
            'decisions.decidedBy:id,name',
        ]);

        $allowedTransitions = $capturePlan->stage->allowedTransitions();

        return Inertia::render('Capture/Show', [
            'capturePlan' => $capturePlan,
            'allowedTransitions' => collect($allowedTransitions)->map(fn($s) => ['value' => $s->value, 'label' => $s->label(), 'color' => $s->color()]),
            'stages' => collect(CaptureStage::cases())->map(fn($s) => ['value' => $s->value, 'label' => $s->label(), 'order' => $s->order(), 'color' => $s->color()]),
            'can' => ['manage' => $request->user()->can('manage', $capturePlan)],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', CapturePlan::class);

        $validated = $request->validate([
            'opportunity_id' => 'required|exists:opportunities,id',
            'capture_manager_id' => 'nullable|exists:users,id',
            'probability_of_win' => 'nullable|numeric|min:0|max:100',
            'estimated_value' => 'nullable|numeric|min:0',
            'strategy' => 'nullable|string',
        ]);

        $user = $request->user();
        $opportunity = Opportunity::findOrFail($validated['opportunity_id']);

        abort_unless($opportunity->organization_id === $user->organization_id, 403);

        if (CapturePlan::where('opportunity_id', $opportunity->id)->exists()) {
            return back()->withErrors(['opportunity_id' => 'This opportunity already has a capture plan.']);
        }

        $plan = CapturePlan::create([
            ...$validated,
            'organization_id' => $user->organization_id,
            'created_by' => $user->id,
            'stage' => 'discovery',
        ]);

        $plan->stageHistory()->create([
            'changed_by' => $user->id,
            'from_stage' => null,
            'to_stage' => 'discovery',
            'changed_at' => now(),
        ]);

        return redirect()->route('capture.show', $plan)->with('success', 'Capture plan created.');
    }

    public function transition(Request $request, CapturePlan $capturePlan): RedirectResponse
    {
        $this->authorize('manage', $capturePlan);

        $validated = $request->validate([
            'stage' => 'required|string',
            'notes' => 'nullable|string',
        ]);

        try {
            $this->workflow->transitionStage($capturePlan, CaptureStage::from($validated['stage']), $request->user(), $validated['notes'] ?? null);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return redirect()->route('capture.show', $capturePlan)->with('success', 'Capture stage updated.');
    }

    public function update(Request $request, CapturePlan $capturePlan): RedirectResponse
    {
        $this->authorize('update', $capturePlan);

        $validated = $request->validate([
            'capture_manager_id' => 'nullable|exists:users,id',
            'probability_of_win' => 'nullable|numeric|min:0|max:100',
            'estimated_value' => 'nullable|numeric|min:0',
            'strategy' => 'nullable|string',
            'win_themes' => 'nullable|string',
            'discriminators' => 'nullable|string',
        ]);

        $capturePlan->update($validated);

        return redirect()->route('capture.show', $capturePlan)->with('success', 'Capture plan updated.');
    }
}
