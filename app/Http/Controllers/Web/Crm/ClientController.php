<?php

namespace App\Http\Controllers\Web\Crm;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Crm\Invoice;
use App\Models\Crm\Lead;
use App\Models\Crm\Project;
use App\Models\Opportunity;
use App\Models\ProposalMailing;
use App\Models\ProposalSubmission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Clients" in the CRM section are the shared `companies` records, surfaced under
 * /crm with their own layout. Reuses the Company model + CompanyPolicy.
 */
class ClientController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Company::class);
        $user = $request->user();

        $clients = Company::where('organization_id', $user->organization_id)
            ->when($request->search, fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->when($request->type, fn ($q, $t) => $q->where('company_type', $t))
            ->withCount('contacts')
            ->orderBy('name')
            ->paginate(15)->withQueryString();

        return Inertia::render('Crm/Clients/Index', [
            'clients' => $clients,
            'filters' => $request->only(['search', 'type']),
            'can' => ['manage' => $user->can('manage companies')],
        ]);
    }

    public function show(Request $request, Company $company): Response
    {
        $this->authorize('view', $company);
        $orgId = $request->user()->organization_id;

        $company->load(['contacts' => fn ($q) => $q->orderBy('last_name')]);

        return Inertia::render('Crm/Clients/Show', [
            'client' => $company,
            'leads' => Lead::where('organization_id', $orgId)->where('company_id', $company->id)
                ->latest()->limit(10)->get()->map(fn (Lead $l) => [
                    'id' => $l->id, 'title' => $l->title, 'value' => (float) $l->estimated_value,
                    'status_label' => $l->status->label(), 'status_color' => $l->status->color(),
                ]),
            'projects' => Project::where('organization_id', $orgId)->where('company_id', $company->id)
                ->latest()->limit(10)->get()->map(fn (Project $p) => [
                    'id' => $p->id, 'name' => $p->name, 'progress' => $p->progress,
                    'status_label' => $p->status->label(), 'status_color' => $p->status->color(),
                ]),
            'invoices' => Invoice::where('organization_id', $orgId)->where('company_id', $company->id)
                ->latest()->limit(10)->get()->map(fn (Invoice $i) => [
                    'id' => $i->id, 'number' => $i->number, 'kind' => $i->kind,
                    'total' => (float) $i->total, 'currency' => $i->currency,
                    'status_label' => $i->status->label(), 'status_color' => $i->status->color(),
                ]),
            // Cross-platform: this client's records in Proposals & Shipments.
            'proposals' => ProposalSubmission::where('organization_id', $orgId)->where('company_id', $company->id)
                ->latest()->limit(10)->get()->map(fn (ProposalSubmission $p) => [
                    'id' => $p->id, 'number' => $p->proposal_number, 'name' => $p->project_name,
                    'value' => (float) $p->proposal_value,
                    'status_label' => $p->status?->label() ?? '—', 'status_color' => $p->status?->color() ?? 'gray',
                ]),
            'opportunities' => Opportunity::where('organization_id', $orgId)->where('company_id', $company->id)
                ->latest()->limit(10)->get()->map(fn (Opportunity $o) => [
                    'id' => $o->id, 'title' => $o->title, 'value' => (float) $o->estimated_value,
                    'status_label' => $o->status?->label() ?? '—', 'status_color' => $o->status?->color() ?? 'gray',
                ]),
            'shipments' => $this->clientShipments($orgId, $company->id),
            'can' => ['manage' => $request->user()->can('manage companies')],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Company::class);
        $user = $request->user();

        $validated = $this->validateClient($request);
        $company = Company::create([
            ...$validated,
            'organization_id' => $user->organization_id,
            'created_by' => $user->id,
            'owner_id' => $user->id,
        ]);

        return redirect()->route('crm.clients.show', $company)->with('success', 'Client created.');
    }

    public function update(Request $request, Company $company): RedirectResponse
    {
        $this->authorize('update', $company);
        $company->update($this->validateClient($request));

        return back()->with('success', 'Client updated.');
    }

    public function destroy(Request $request, Company $company): RedirectResponse
    {
        $this->authorize('delete', $company);
        $name = $company->name;
        $company->delete();

        return redirect()->route('crm.clients.index')->with('success', "Client \"{$name}\" deleted.");
    }

    /** Shipments for a client = mailings of that client's proposals. */
    private function clientShipments(int $orgId, int $companyId)
    {
        $proposalIds = ProposalSubmission::where('organization_id', $orgId)
            ->where('company_id', $companyId)->pluck('id');

        if ($proposalIds->isEmpty()) {
            return [];
        }

        return ProposalMailing::where('organization_id', $orgId)
            ->whereIn('proposal_submission_id', $proposalIds)
            ->latest()->limit(10)->get()
            ->map(fn (ProposalMailing $m) => [
                'id' => $m->id,
                'ulid' => $m->ulid,
                'tracking' => $m->ups_tracking_number,
                'recipient' => $m->recipient_name,
                'status_label' => $m->status?->label() ?? '—',
                'status_color' => $m->status?->color() ?? 'gray',
            ]);
    }

    /** @return array<string,mixed> */
    private function validateClient(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'company_type' => 'nullable|string|max:100',
            'industry' => 'nullable|string|max:100',
            'cage_code' => 'nullable|string|max:20',
            'website' => 'nullable|url|max:255',
            'phone' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:255',
            'address_line1' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:120',
            'state' => 'nullable|string|max:120',
            'notes' => 'nullable|string',
        ]);
    }
}
