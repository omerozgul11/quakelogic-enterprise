<?php

namespace App\Http\Controllers\Web\Crm;

use App\Enums\LeadStatus;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Crm\Lead;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Inertia\Inertia;
use Inertia\Response;

class LeadController extends Controller
{
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
            'contact_name' => $l->contact_name,
            'company' => $l->company?->name,
            'company_id' => $l->company_id,
            'owner' => $l->owner?->name,
            'email' => $l->email,
            'phone' => $l->phone,
            'source' => $l->source,
            'status' => $l->status->value,
            'estimated_value' => (float) $l->estimated_value,
            'probability' => $l->probability,
            'expected_close_date' => $l->expected_close_date?->toDateString(),
            'notes' => $l->notes,
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
            'companies' => Company::where('organization_id', $user->organization_id)
                ->orderBy('name')->get(['id', 'name']),
            'sources' => ['Website', 'Referral', 'Cold Call', 'Email', 'Event', 'Partner', 'SAM.gov', 'Other'],
            'statuses' => collect(LeadStatus::pipeline())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()]),
            'can' => ['manage' => $user->can('manage leads')],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Lead::class);
        $user = $request->user();

        Lead::create([
            ...$this->validateLead($request),
            'organization_id' => $user->organization_id,
            'created_by' => $user->id,
            'owner_id' => $user->id,
            'last_activity_at' => now(),
        ]);

        return back()->with('success', 'Lead added.');
    }

    public function update(Request $request, Lead $lead): RedirectResponse
    {
        $this->authorize('update', $lead);
        $lead->update([...$this->validateLead($request), 'last_activity_at' => now()]);

        return back()->with('success', 'Lead updated.');
    }

    public function updateStatus(Request $request, Lead $lead): RedirectResponse
    {
        $this->authorize('update', $lead);
        $validated = $request->validate(['status' => ['required', new Enum(LeadStatus::class)]]);
        $lead->update(['status' => $validated['status'], 'last_activity_at' => now()]);

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
                    'name' => $lead->title,
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
            'title' => 'required|string|max:255',
            'contact_name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:30',
            'source' => 'nullable|string|max:100',
            'status' => ['nullable', new Enum(LeadStatus::class)],
            'estimated_value' => 'nullable|numeric|min:0|max:9999999999999',
            'probability' => 'nullable|integer|min:0|max:100',
            'expected_close_date' => 'nullable|date',
            'notes' => 'nullable|string',
            'company_id' => [
                'nullable',
                Rule::exists('companies', 'id')->where('organization_id', $user->organization_id),
            ],
        ]);
    }
}
