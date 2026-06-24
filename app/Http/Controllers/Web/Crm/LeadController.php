<?php

namespace App\Http\Controllers\Web\Crm;

use App\Enums\LeadStatus;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Crm\Activity;
use App\Models\Crm\FollowUp;
use App\Models\Crm\Lead;
use App\Models\User;
use App\Services\Crm\ActivityLogger;
use App\Services\Crm\AutomationEngine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Inertia\Inertia;
use Inertia\Response;

class LeadController extends Controller
{
    public function __construct(
        private readonly ActivityLogger $activity,
        private readonly AutomationEngine $automations,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Lead::class);
        $user = $request->user();

        $leads = Lead::where('organization_id', $user->organization_id)
            ->with(['company:id,name', 'owner:id,name'])
            ->when($request->search, fn ($q, $s) => $q->where(fn ($w) => $w
                ->where('title', 'like', "%{$s}%")
                ->orWhere('contact_name', 'like', "%{$s}%")
                ->orWhere('email', 'like', "%{$s}%")))
            ->orderByDesc('updated_at')
            ->get();

        $shaped = $leads->map(fn (Lead $l) => [
            'id' => $l->id,
            'title' => $l->title,
            'company' => $l->company_name ?: $l->company?->name,
            'contact_name' => $l->contact_name,
            'product' => $l->product_name,
            'owner' => $l->owner?->name,
            'owner_id' => $l->owner_id,
            'email' => $l->email,
            'phone' => $l->phone,
            'source' => $l->source,
            'status' => $l->status->value,
            'estimated_value' => (float) $l->estimated_value,
            'probability' => $l->probability,
            'expected_close_date' => $l->expected_close_date?->toDateString(),
            'notes' => $l->notes,
            'created_at' => $l->created_at?->toIso8601String(),
        ]);

        $columns = collect(LeadStatus::pipeline())->map(fn (LeadStatus $s) => [
            'key' => $s->value,
            'label' => $s->label(),
            'color' => $s->color(),
            'leads' => $shaped->where('status', $s->value)->values(),
            'value' => (float) $shaped->where('status', $s->value)->sum('estimated_value'),
        ])->values();

        return Inertia::render('Crm/Leads/Index', [
            'columns' => $columns,
            'total' => $shaped->count(),
            'owners' => User::where('organization_id', $user->organization_id)
                ->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'currentUserId' => $user->id,
            'sources' => ['Website', 'Referral', 'Cold Call', 'Email', 'Event', 'Partner', 'SAM.gov', 'Other'],
            'statuses' => collect(LeadStatus::pipeline())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()]),
            // Leads are open to every CRM user (the section is gated by access crm).
            'can' => ['manage' => $user->can('access crm')],
        ]);
    }

    public function show(Request $request, Lead $lead): Response
    {
        $this->authorize('view', $lead);
        $user = $request->user();
        abort_unless($lead->organization_id === $user->organization_id, 403);

        $lead->load(['company:id,name', 'contact:id,first_name,last_name', 'owner:id,name']);

        $activities = Activity::forOrganization($user->organization_id)
            ->where('subject_type', $lead->getMorphClass())
            ->where('subject_id', $lead->id)
            ->with('user:id,name')
            ->orderByDesc('happened_at')
            ->limit(200)
            ->get()
            ->map(fn (Activity $a) => [
                'id' => $a->id,
                'type' => $a->type,
                'body' => $a->body,
                'meta' => $a->meta,
                'user' => $a->user?->name,
                'user_id' => $a->user_id,
                'happened_at' => $a->happened_at?->toIso8601String(),
                'can_delete' => $a->user_id === $user->id && in_array($a->type, Activity::MANUAL_TYPES, true),
            ]);

        return Inertia::render('Crm/Leads/Show', [
            'lead' => [
                'id' => $lead->id,
                'title' => $lead->title,
                'company' => $lead->company_name ?: $lead->company?->name,
                'company_id' => $lead->company_id,
                'contact_name' => $lead->contact_name ?: $lead->contact?->full_name,
                'product' => $lead->product_name,
                'email' => $lead->email,
                'phone' => $lead->phone,
                'source' => $lead->source,
                'status' => $lead->status->value,
                'status_label' => $lead->status->label(),
                'status_color' => $lead->status->color(),
                'estimated_value' => (float) $lead->estimated_value,
                'probability' => $lead->probability,
                'expected_close_date' => $lead->expected_close_date?->toDateString(),
                'owner' => $lead->owner?->name,
                'owner_id' => $lead->owner_id,
                'notes' => $lead->notes,
                'created_at' => $lead->created_at?->toIso8601String(),
            ],
            'activities' => $activities,
            'followUps' => FollowUp::forOrganization($user->organization_id)
                ->where('subject_type', $lead->getMorphClass())
                ->where('subject_id', $lead->id)
                ->with('assignee:id,name')
                ->orderByRaw("status = 'done'")
                ->orderBy('due_date')
                ->get()
                ->map(fn (FollowUp $f) => [
                    'id' => $f->id,
                    'title' => $f->title,
                    'notes' => $f->notes,
                    'due_date' => $f->due_date?->toDateString(),
                    'priority' => $f->priority,
                    'status' => $f->status,
                    'is_overdue' => $f->isOverdue(),
                    'assigned_to' => $f->assigned_to,
                    'assignee' => $f->assignee?->name,
                ]),
            'statuses' => collect(LeadStatus::pipeline())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()]),
            'sources' => ['Website', 'Referral', 'Cold Call', 'Email', 'Event', 'Partner', 'SAM.gov', 'Other'],
            'owners' => User::where('organization_id', $user->organization_id)
                ->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'currentUserId' => $user->id,
            'priorities' => FollowUp::PRIORITIES,
            'can' => ['manage' => $user->can('access crm')],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Lead::class);
        $user = $request->user();
        $data = $this->validateLead($request);

        $lead = Lead::create([
            ...$data,
            'organization_id' => $user->organization_id,
            'created_by' => $user->id,
            'owner_id' => $data['owner_id'] ?? $user->id,
            'title' => $data['company_name'],
            // Set the stage explicitly so the in-memory model is hydrated (the DB
            // default isn't reflected on the instance the hooks below receive).
            'status' => $data['status'] ?? LeadStatus::New->value,
            'last_activity_at' => now(),
        ]);

        $this->activity->created($lead, $user, "Lead created — {$lead->title}");
        $this->automations->leadCreated($lead, $user);

        return back()->with('success', 'Lead added.');
    }

    public function update(Request $request, Lead $lead): RedirectResponse
    {
        $this->authorize('update', $lead);
        $data = $this->validateLead($request);

        $lead->update([
            ...$data,
            'owner_id' => $data['owner_id'] ?? $lead->owner_id,
            'title' => $data['company_name'],
            'last_activity_at' => now(),
        ]);

        return back()->with('success', 'Lead updated.');
    }

    public function updateStatus(Request $request, Lead $lead): RedirectResponse
    {
        $this->authorize('update', $lead);
        $validated = $request->validate(['status' => ['required', new Enum(LeadStatus::class)]]);

        $from = $lead->status;
        $to = LeadStatus::from($validated['status']);
        $lead->update(['status' => $to, 'last_activity_at' => now()]);

        if ($from !== $to) {
            $this->activity->stageChanged($lead, $from, $to, $request->user());
            $this->automations->leadStageChanged($lead, $to, $request->user());
        }

        return back()->with('success', 'Lead moved to '.$lead->status->label().'.');
    }

    public function convert(Request $request, Lead $lead): RedirectResponse
    {
        $this->authorize('update', $lead);
        $user = $request->user();

        DB::transaction(function () use ($lead, $user) {
            $companyId = $lead->company_id;

            if (! $companyId) {
                $company = Company::create([
                    'organization_id' => $user->organization_id,
                    'created_by' => $user->id,
                    'owner_id' => $user->id,
                    'name' => $lead->company_name ?: $lead->title,
                    'company_type' => 'client',
                    'phone' => $lead->phone,
                    'email' => $lead->email,
                ]);
                $companyId = $company->id;
            }

            if ($lead->contact_name && ! $lead->contact_id) {
                $parts = preg_split('/\s+/', trim($lead->contact_name), 2);
                $contact = Contact::create([
                    'organization_id' => $user->organization_id,
                    'created_by' => $user->id,
                    'owner_id' => $user->id,
                    'company_id' => $companyId,
                    'first_name' => $parts[0] ?? $lead->contact_name,
                    'last_name' => $parts[1] ?? '—',
                    'email' => $lead->email,
                    'phone' => $lead->phone,
                ]);
                $lead->contact_id = $contact->id;
            }

            $lead->update([
                'company_id' => $companyId,
                'contact_id' => $lead->contact_id,
                'status' => LeadStatus::Won,
                'last_activity_at' => now(),
            ]);
        });

        $this->activity->converted($lead, $user);
        $this->automations->leadStageChanged($lead->refresh(), LeadStatus::Won, $user);

        return back()->with('success', 'Lead converted — client created.');
    }

    public function destroy(Request $request, Lead $lead): RedirectResponse
    {
        $this->authorize('delete', $lead);
        $lead->delete();

        return back()->with('success', 'Lead deleted.');
    }

    /** @return array<string,mixed> */
    private function validateLead(Request $request): array
    {
        $user = $request->user();

        return $request->validate([
            // The important details — required so a lead can't be saved half-empty.
            'company_name' => 'required|string|max:255',
            'contact_name' => 'required|string|max:255',
            'phone' => 'required|string|max:30',
            'product_name' => 'required|string|max:255',
            'owner_id' => [
                'nullable',
                Rule::exists('users', 'id')->where('organization_id', $user->organization_id),
            ],
            'email' => 'nullable|email|max:255',
            'source' => 'nullable|string|max:100',
            'status' => ['nullable', new Enum(LeadStatus::class)],
            'estimated_value' => 'nullable|numeric|min:0|max:9999999999999',
            'probability' => 'nullable|integer|min:0|max:100',
            'expected_close_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ], [
            'company_name.required' => 'Enter the company name.',
            'contact_name.required' => 'Enter the lead (contact person) name.',
            'phone.required' => 'Enter a phone number.',
            'product_name.required' => 'Enter the product.',
        ]);
    }
}
