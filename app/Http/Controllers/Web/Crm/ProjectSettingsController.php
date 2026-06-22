<?php

namespace App\Http\Controllers\Web\Crm;

use App\Enums\ProjectStatus;
use App\Http\Controllers\Controller;
use App\Models\Crm\Project;
use App\Models\Crm\ProjectSetting;
use App\Models\User;
use App\Services\Crm\ProjectCreationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Org-level Project Management settings — admin only (`manage all projects`).
 * Governs the award→project automation, default status/manager rule, numbering
 * and notifications.
 */
class ProjectSettingsController extends Controller
{
    public function __construct(private ProjectCreationService $creation) {}

    public function edit(Request $request): Response
    {
        $this->authorize('manageSettings', Project::class);
        $user = $request->user();
        $settings = $this->creation->settingsFor($user->organization_id);

        return Inertia::render('Crm/Projects/Settings', [
            'settings' => [
                'auto_create_on_award' => $settings->auto_create_on_award,
                'default_status' => $settings->default_status,
                'default_manager_rule' => $settings->default_manager_rule,
                'number_prefix' => $settings->number_prefix,
                'notify_on_create' => $settings->notify_on_create,
                'default_member_ids' => array_map('intval', (array) $settings->default_member_ids),
            ],
            'statuses' => collect(ProjectStatus::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()]),
            'managerRules' => [
                ['value' => 'proposal_owner', 'label' => 'Proposal owner becomes project owner & manager'],
                ['value' => 'proposal_creator', 'label' => 'Proposal creator becomes the manager'],
                ['value' => 'unassigned', 'label' => 'Leave the project manager unassigned'],
            ],
            'users' => User::where('organization_id', $user->organization_id)
                ->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorize('manageSettings', Project::class);
        $user = $request->user();

        $validated = $request->validate([
            'auto_create_on_award' => 'boolean',
            'default_status' => ['required', new Enum(ProjectStatus::class)],
            'default_manager_rule' => ['required', Rule::in(['proposal_owner', 'proposal_creator', 'unassigned'])],
            'number_prefix' => 'required|string|max:20|regex:/^[A-Za-z0-9\-]+$/',
            'notify_on_create' => 'boolean',
            'default_member_ids' => 'nullable|array',
            'default_member_ids.*' => [Rule::exists('users', 'id')->where('organization_id', $user->organization_id)],
        ]);

        $settings = ProjectSetting::firstOrCreate(['organization_id' => $user->organization_id]);
        $settings->update([
            'auto_create_on_award' => $request->boolean('auto_create_on_award'),
            'default_status' => $validated['default_status'],
            'default_manager_rule' => $validated['default_manager_rule'],
            'number_prefix' => rtrim($validated['number_prefix'], '-'),
            'notify_on_create' => $request->boolean('notify_on_create'),
            'default_member_ids' => array_values(array_map('intval', $validated['default_member_ids'] ?? [])),
        ]);

        return back()->with('success', 'Project settings saved.');
    }
}
