<?php

namespace App\Http\Controllers\Web\Crm;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Crm\FollowUp;
use App\Models\Crm\Lead;
use App\Models\User;
use App\Services\Crm\ActivityLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * CRM follow-up tasks and the Today/Overdue/Upcoming queue. Follow-ups are open
 * to every CRM user in the org (the section is gated by `access crm`), matching
 * how leads are shared.
 */
class FollowUpController extends Controller
{
    private const SUBJECTS = [
        'lead' => Lead::class,
        'company' => Company::class,
        'contact' => Contact::class,
    ];

    public function index(Request $request): Response
    {
        $user = $request->user();
        $scope = $request->query('scope') === 'all' ? 'all' : 'mine';

        $query = FollowUp::forOrganization($user->organization_id)
            ->with(['assignee:id,name', 'subject'])
            ->orderByRaw("status = 'done'") // open first
            ->orderBy('due_date');

        if ($scope === 'mine') {
            $query->where('assigned_to', $user->id);
        }

        $rows = $query->limit(500)->get()->map(fn (FollowUp $f) => $this->shape($f));

        return Inertia::render('Crm/FollowUps/Index', [
            'followUps' => $rows,
            'scope' => $scope,
            'owners' => User::where('organization_id', $user->organization_id)
                ->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'currentUserId' => $user->id,
            'priorities' => FollowUp::PRIORITIES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $this->validateFollowUp($request);

        $attrs = [
            'organization_id' => $user->organization_id,
            'created_by' => $user->id,
            'assigned_to' => $data['assigned_to'] ?? $user->id,
            'title' => $data['title'],
            'notes' => $data['notes'] ?? null,
            'due_date' => $data['due_date'],
            'priority' => $data['priority'] ?? 'normal',
            'status' => 'open',
        ];

        if (! empty($data['subject']) && ! empty($data['subject_id'])) {
            $record = $this->resolveSubject($user->organization_id, $data['subject'], (int) $data['subject_id']);
            $attrs['subject_type'] = $record->getMorphClass();
            $attrs['subject_id'] = $record->getKey();
        }

        FollowUp::create($attrs);

        return back()->with('success', 'Follow-up added.');
    }

    public function update(Request $request, FollowUp $followUp): RedirectResponse
    {
        $this->authorizeOrg($request, $followUp);
        $data = $this->validateFollowUp($request);

        $followUp->update([
            'title' => $data['title'],
            'notes' => $data['notes'] ?? null,
            'due_date' => $data['due_date'],
            'priority' => $data['priority'] ?? $followUp->priority,
            'assigned_to' => $data['assigned_to'] ?? $followUp->assigned_to,
        ]);

        return back()->with('success', 'Follow-up updated.');
    }

    /** Toggle done/open. Completing logs a timeline entry on the linked record. */
    public function complete(Request $request, FollowUp $followUp, ActivityLogger $logger): RedirectResponse
    {
        $this->authorizeOrg($request, $followUp);
        $user = $request->user();

        if ($followUp->status === 'open') {
            $followUp->update([
                'status' => 'done',
                'completed_at' => now(),
                'completed_by' => $user->id,
            ]);

            $subject = $this->subjectModel($followUp);
            if ($subject) {
                $logger->log($subject, 'task', "Completed follow-up: {$followUp->title}", $user, ['follow_up_id' => $followUp->id]);
            }

            return back()->with('success', 'Follow-up completed.');
        }

        $followUp->update(['status' => 'open', 'completed_at' => null, 'completed_by' => null]);

        return back()->with('success', 'Follow-up reopened.');
    }

    public function destroy(Request $request, FollowUp $followUp): RedirectResponse
    {
        $this->authorizeOrg($request, $followUp);
        $followUp->delete();

        return back()->with('success', 'Follow-up removed.');
    }

    /** @return array<string,mixed> */
    private function shape(FollowUp $f): array
    {
        $subject = $f->subject;
        $subjectLabel = null;
        $subjectLink = null;
        if ($subject instanceof Lead) {
            $subjectLabel = $subject->title;
            $subjectLink = "/crm/leads/{$subject->id}";
        } elseif ($subject instanceof Company) {
            $subjectLabel = $subject->name;
            $subjectLink = "/crm/clients/{$subject->id}";
        } elseif ($subject instanceof Contact) {
            $subjectLabel = trim("{$subject->first_name} {$subject->last_name}");
        }

        return [
            'id' => $f->id,
            'title' => $f->title,
            'notes' => $f->notes,
            'due_date' => $f->due_date?->toDateString(),
            'priority' => $f->priority,
            'status' => $f->status,
            'is_overdue' => $f->isOverdue(),
            'assigned_to' => $f->assigned_to,
            'assignee' => $f->assignee?->name,
            'subject_label' => $subjectLabel,
            'subject_link' => $subjectLink,
        ];
    }

    /** @return array<string,mixed> */
    private function validateFollowUp(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'due_date' => ['required', 'date'],
            'priority' => ['nullable', Rule::in(FollowUp::PRIORITIES)],
            'assigned_to' => [
                'nullable',
                Rule::exists('users', 'id')->where('organization_id', $request->user()->organization_id),
            ],
            'subject' => ['nullable', Rule::in(array_keys(self::SUBJECTS))],
            'subject_id' => ['nullable', 'integer'],
        ]);
    }

    private function resolveSubject(int $orgId, string $key, int $id): Model
    {
        $model = self::SUBJECTS[$key];

        return $model::where('organization_id', $orgId)->findOrFail($id);
    }

    private function subjectModel(FollowUp $followUp): ?Model
    {
        return $followUp->subject_type ? $followUp->subject : null;
    }

    private function authorizeOrg(Request $request, FollowUp $followUp): void
    {
        abort_unless($followUp->organization_id === $request->user()->organization_id, 403);
    }

    /** Open follow-up queue for one user, bucketed for the dashboard widget. */
    public static function queueFor(User $user): array
    {
        $today = Carbon::now()->toDateString();

        $open = FollowUp::forOrganization($user->organization_id)
            ->where('assigned_to', $user->id)
            ->where('status', 'open')
            ->with(['subject', 'assignee:id,name'])
            ->orderBy('due_date')
            ->limit(50)
            ->get();

        $bucket = fn (FollowUp $f) => $f->due_date?->toDateString() < $today
            ? 'overdue'
            : ($f->due_date?->toDateString() === $today ? 'today' : 'upcoming');

        $controller = new self;
        $grouped = $open->groupBy($bucket);

        return [
            'overdue' => $grouped->get('overdue', collect())->map(fn ($f) => $controller->shape($f))->values(),
            'today' => $grouped->get('today', collect())->map(fn ($f) => $controller->shape($f))->values(),
            'upcoming' => $grouped->get('upcoming', collect())->take(5)->map(fn ($f) => $controller->shape($f))->values(),
            'counts' => [
                'overdue' => $grouped->get('overdue', collect())->count(),
                'today' => $grouped->get('today', collect())->count(),
                'upcoming' => $grouped->get('upcoming', collect())->count(),
            ],
        ];
    }
}
