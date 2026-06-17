<?php

namespace App\Http\Controllers\Web\Crm;

use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Crm\Project;
use App\Models\Crm\Task;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Project::class);
        $user = $request->user();

        $projects = Project::where('organization_id', $user->organization_id)
            ->with(['company:id,name', 'owner:id,name'])
            ->withCount(['tasks', 'tasks as completed_tasks_count' => fn ($q) => $q->where('status', TaskStatus::Completed->value)])
            ->when($request->search, fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->when($request->status, fn ($q, $st) => $q->where('status', $st))
            ->orderByDesc('updated_at')
            ->paginate(15)->withQueryString()
            ->through(fn (Project $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'code' => $p->code,
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
                'tasks_count' => $p->tasks_count,
                'completed_tasks_count' => $p->completed_tasks_count,
            ]);

        return Inertia::render('Crm/Projects/Index', [
            'projects' => $projects,
            'filters' => $request->only(['search', 'status']),
            'companies' => Company::where('organization_id', $user->organization_id)->orderBy('name')->get(['id', 'name']),
            'owners' => $this->orgUsers($user->organization_id),
            'statuses' => $this->statusOptions(),
            'can' => ['manage' => $user->can('manage projects')],
        ]);
    }

    public function show(Request $request, Project $project): Response
    {
        $this->authorize('view', $project);
        $user = $request->user();

        $project->load(['company:id,name', 'owner:id,name', 'tasks.assignee:id,name']);

        $tasks = $project->tasks->map(fn (Task $t) => [
            'id' => $t->id,
            'title' => $t->title,
            'description' => $t->description,
            'status' => $t->status->value,
            'status_label' => $t->status->label(),
            'status_color' => $t->status->color(),
            'priority' => $t->priority,
            'due_date' => $t->due_date?->toDateString(),
            'assigned_to' => $t->assigned_to,
            'assignee' => $t->assignee?->name,
        ]);

        return Inertia::render('Crm/Projects/Show', [
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'code' => $project->code,
                'description' => $project->description,
                'status' => $project->status->value,
                'status_label' => $project->status->label(),
                'status_color' => $project->status->color(),
                'progress' => $project->progress,
                'budget' => (float) $project->budget,
                'start_date' => $project->start_date?->toDateString(),
                'due_date' => $project->due_date?->toDateString(),
                'company' => $project->company?->name,
                'company_id' => $project->company_id,
                'owner' => $project->owner?->name,
                'owner_id' => $project->owner_id,
            ],
            'tasks' => $tasks,
            'companies' => Company::where('organization_id', $user->organization_id)->orderBy('name')->get(['id', 'name']),
            'owners' => $this->orgUsers($user->organization_id),
            'statuses' => $this->statusOptions(),
            'taskStatuses' => collect(TaskStatus::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label(), 'color' => $s->color()]),
            'can' => ['manage' => $user->can('manage projects')],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Project::class);
        $user = $request->user();

        $project = Project::create([
            ...$this->validateProject($request),
            'organization_id' => $user->organization_id,
            'created_by' => $user->id,
        ]);

        return redirect()->route('crm.projects.show', $project)->with('success', 'Project created.');
    }

    public function update(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $data = $this->validateProject($request);
        if (($data['status'] ?? null) === ProjectStatus::Completed->value && ! $project->completed_at) {
            $data['completed_at'] = now();
        }
        $project->update($data);

        return back()->with('success', 'Project updated.');
    }

    public function destroy(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('delete', $project);
        $name = $project->name;
        $project->delete();

        return redirect()->route('crm.projects.index')->with('success', "Project \"{$name}\" deleted.");
    }

    public function storeTask(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);
        $user = $request->user();

        $project->tasks()->create([
            ...$this->validateTask($request, $user->organization_id),
            'organization_id' => $user->organization_id,
            'created_by' => $user->id,
            'position' => (int) $project->tasks()->max('position') + 1,
        ]);

        $this->recomputeProgress($project);

        return back()->with('success', 'Task added.');
    }

    public function updateTask(Request $request, Project $project, Task $task): RedirectResponse
    {
        $this->authorize('update', $project);
        abort_unless($task->crm_project_id === $project->id, 404);

        $data = $this->validateTask($request, $request->user()->organization_id);
        if (($data['status'] ?? null) === TaskStatus::Completed->value && ! $task->completed_at) {
            $data['completed_at'] = now();
        } elseif (($data['status'] ?? null) && $data['status'] !== TaskStatus::Completed->value) {
            $data['completed_at'] = null;
        }
        $task->update($data);

        $this->recomputeProgress($project);

        return back()->with('success', 'Task updated.');
    }

    public function destroyTask(Request $request, Project $project, Task $task): RedirectResponse
    {
        $this->authorize('update', $project);
        abort_unless($task->crm_project_id === $project->id, 404);
        $task->delete();

        $this->recomputeProgress($project);

        return back()->with('success', 'Task deleted.');
    }

    /** Recompute % complete from task completion (silent — no audit churn). */
    private function recomputeProgress(Project $project): void
    {
        $total = $project->tasks()->count();
        $done = $project->tasks()->where('status', TaskStatus::Completed->value)->count();
        $project->progress = $total > 0 ? (int) round($done / $total * 100) : $project->progress;
        $project->saveQuietly();
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
            'start_date' => 'nullable|date',
            'due_date' => 'nullable|date',
            'budget' => 'nullable|numeric|min:0|max:9999999999999',
            'company_id' => ['nullable', Rule::exists('companies', 'id')->where('organization_id', $user->organization_id)],
            'owner_id' => ['nullable', Rule::exists('users', 'id')->where('organization_id', $user->organization_id)],
        ]);
    }

    /** @return array<string,mixed> */
    private function validateTask(Request $request, int $orgId): array
    {
        return $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => ['nullable', new Enum(TaskStatus::class)],
            'priority' => 'nullable|in:low,medium,high,urgent',
            'due_date' => 'nullable|date',
            'assigned_to' => ['nullable', Rule::exists('users', 'id')->where('organization_id', $orgId)],
        ]);
    }

    private function orgUsers(int $orgId)
    {
        return User::where('organization_id', $orgId)->orderBy('name')->get(['id', 'name']);
    }

    private function statusOptions(): array
    {
        return collect(ProjectStatus::cases())
            ->map(fn ($s) => ['value' => $s->value, 'label' => $s->label(), 'color' => $s->color()])
            ->all();
    }
}
