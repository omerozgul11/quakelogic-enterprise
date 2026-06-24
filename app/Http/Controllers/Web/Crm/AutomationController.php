<?php

namespace App\Http\Controllers\Web\Crm;

use App\Enums\LeadStatus;
use App\Http\Controllers\Controller;
use App\Models\Crm\Automation;
use App\Models\Crm\FollowUp;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * CRUD for CRM automation rules. Building/editing rules is a management action
 * gated by `manage all time cards` (the same admin capability used elsewhere in
 * the CRM); everyone else just benefits from the rules running.
 */
class AutomationController extends Controller
{
    private const MANAGE = 'manage all time cards';

    private const TRIGGER_LABELS = [
        'lead.created' => 'When a lead is created',
        'lead.stage_changed' => 'When a lead changes stage',
    ];

    public function index(Request $request): Response
    {
        $user = $request->user();
        $canManage = $user->can(self::MANAGE);

        $automations = Automation::forOrganization($user->organization_id)
            ->orderByDesc('is_active')->orderBy('name')
            ->get()
            ->map(fn (Automation $a) => [
                'id' => $a->id,
                'name' => $a->name,
                'is_active' => $a->is_active,
                'trigger_event' => $a->trigger_event,
                'trigger_label' => self::TRIGGER_LABELS[$a->trigger_event] ?? $a->trigger_event,
                'conditions' => $a->conditions ?? [],
                'actions' => $a->actions ?? [],
                'run_count' => $a->run_count,
                'last_run_at' => $a->last_run_at?->toIso8601String(),
            ]);

        return Inertia::render('Crm/Automations/Index', [
            'automations' => $automations,
            'canManage' => $canManage,
            'options' => [
                'triggers' => collect(Automation::TRIGGERS)->map(fn ($t) => ['value' => $t, 'label' => self::TRIGGER_LABELS[$t] ?? $t]),
                'stages' => collect(LeadStatus::pipeline())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()]),
                'sources' => ['Website', 'Referral', 'Cold Call', 'Email', 'Event', 'Partner', 'SAM.gov', 'Other'],
                'priorities' => FollowUp::PRIORITIES,
                'actionTypes' => [
                    ['value' => 'create_followup', 'label' => 'Create a follow-up'],
                    ['value' => 'notify', 'label' => 'Notify someone'],
                    ['value' => 'assign_owner', 'label' => 'Assign owner'],
                    ['value' => 'log_activity', 'label' => 'Log a note'],
                ],
                'users' => User::where('organization_id', $user->organization_id)
                    ->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->can(self::MANAGE), 403);

        $data = $this->validateAutomation($request);

        Automation::create([
            'organization_id' => $user->organization_id,
            'created_by' => $user->id,
            'name' => $data['name'],
            'is_active' => $data['is_active'] ?? true,
            'trigger_event' => $data['trigger_event'],
            // Read conditions/actions from raw input — validate() strips sub-keys
            // that have no explicit rules; cleanConditions/cleanActions sanitize.
            'conditions' => $this->cleanConditions($request->input('conditions', [])),
            'actions' => $this->cleanActions($request->input('actions', [])),
        ]);

        return back()->with('success', 'Automation created.');
    }

    public function update(Request $request, Automation $automation): RedirectResponse
    {
        $this->authorizeManage($request, $automation);
        $data = $this->validateAutomation($request);

        $automation->update([
            'name' => $data['name'],
            'is_active' => $data['is_active'] ?? $automation->is_active,
            'trigger_event' => $data['trigger_event'],
            'conditions' => $this->cleanConditions($request->input('conditions', [])),
            'actions' => $this->cleanActions($request->input('actions', [])),
        ]);

        return back()->with('success', 'Automation updated.');
    }

    public function toggle(Request $request, Automation $automation): RedirectResponse
    {
        $this->authorizeManage($request, $automation);
        $automation->update(['is_active' => ! $automation->is_active]);

        return back()->with('success', $automation->is_active ? 'Automation activated.' : 'Automation paused.');
    }

    public function destroy(Request $request, Automation $automation): RedirectResponse
    {
        $this->authorizeManage($request, $automation);
        $automation->delete();

        return back()->with('success', 'Automation removed.');
    }

    /** @return array<string,mixed> */
    private function validateAutomation(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['boolean'],
            'trigger_event' => ['required', Rule::in(Automation::TRIGGERS)],
            'conditions' => ['nullable', 'array'],
            'conditions.stage' => ['nullable', 'string'],
            'conditions.source' => ['nullable', 'string'],
            'conditions.min_value' => ['nullable', 'numeric'],
            'actions' => ['required', 'array', 'min:1'],
            'actions.*.type' => ['required', Rule::in(Automation::ACTION_TYPES)],
        ]);
    }

    /** @param array<string,mixed> $conditions */
    private function cleanConditions(array $conditions): array
    {
        return array_filter([
            'stage' => $conditions['stage'] ?? null,
            'source' => $conditions['source'] ?? null,
            'min_value' => ($conditions['min_value'] ?? '') === '' ? null : $conditions['min_value'],
        ], fn ($v) => $v !== null && $v !== '');
    }

    /**
     * Keep only the fields each action type uses, so stray inputs never persist.
     *
     * @param array<int, array<string,mixed>> $actions
     * @return array<int, array<string,mixed>>
     */
    private function cleanActions(array $actions): array
    {
        return collect($actions)->map(function (array $a) {
            return match ($a['type']) {
                'create_followup' => [
                    'type' => 'create_followup',
                    'title' => (string) ($a['title'] ?? 'Follow up'),
                    'due_in_days' => (int) ($a['due_in_days'] ?? 0),
                    'priority' => $a['priority'] ?? 'normal',
                    'assign' => $a['assign'] ?? 'owner',
                ],
                'notify' => [
                    'type' => 'notify',
                    'to' => $a['to'] ?? 'owner',
                    'message' => (string) ($a['message'] ?? ''),
                ],
                'assign_owner' => [
                    'type' => 'assign_owner',
                    'user_id' => $a['user_id'] ?? null,
                ],
                'log_activity' => [
                    'type' => 'log_activity',
                    'body' => (string) ($a['body'] ?? ''),
                ],
                default => ['type' => $a['type']],
            };
        })->values()->all();
    }

    private function authorizeManage(Request $request, Automation $automation): void
    {
        abort_unless(
            $request->user()->can(self::MANAGE) && $automation->organization_id === $request->user()->organization_id,
            403
        );
    }
}
