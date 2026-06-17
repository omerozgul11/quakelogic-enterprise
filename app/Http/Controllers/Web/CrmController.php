<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\Company;
use App\Models\Contact;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CrmController extends Controller
{
    // Agencies
    public function agenciesIndex(Request $request): Response
    {
        $this->authorize('viewAny', Agency::class);
        $user = $request->user();

        $agencies = Agency::where('organization_id', $user->organization_id)
            ->when($request->search, fn($q, $s) => $q->where('name', 'like', "%{$s}%")->orWhere('acronym', 'like', "%{$s}%"))
            ->withCount(['contacts', 'opportunities', 'proposals'])
            ->orderBy('name')
            ->paginate(25)->withQueryString();

        return Inertia::render('Agencies/Index', [
            'agencies' => $agencies,
            'filters' => $request->only(['search']),
            'can' => ['manage' => $user->can('manage agencies')],
        ]);
    }

    public function agencyShow(Request $request, Agency $agency): Response
    {
        $this->authorize('view', $agency);

        $agency->load(['contacts', 'opportunities' => fn($q) => $q->latest()->limit(10), 'proposals' => fn($q) => $q->latest()->limit(10)]);

        return Inertia::render('Agencies/Show', [
            'agency' => $agency,
            'can' => ['manage' => $request->user()->can('manage agencies')],
        ]);
    }

    public function agencyStore(Request $request): RedirectResponse
    {
        $this->authorize('create', Agency::class);
        $user = $request->user();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'acronym' => 'nullable|string|max:30',
            'federal_code' => 'nullable|string|max:20',
            'website' => 'nullable|url',
            'phone' => 'nullable|string|max:30',
            'email' => 'nullable|email',
            'notes' => 'nullable|string',
        ]);

        Agency::create([...$validated, 'organization_id' => $user->organization_id, 'created_by' => $user->id]);

        return redirect()->route('agencies.index')->with('success', 'Agency created.');
    }

    public function agencyUpdate(Request $request, Agency $agency): RedirectResponse
    {
        $this->authorize('update', $agency);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'acronym' => 'nullable|string|max:30',
            'federal_code' => 'nullable|string|max:20',
            'website' => 'nullable|url',
            'phone' => 'nullable|string|max:30',
            'email' => 'nullable|email',
            'notes' => 'nullable|string',
        ]);

        $agency->update($validated);

        return redirect()->route('agencies.show', $agency)->with('success', 'Agency updated.');
    }

    // Companies
    public function companiesIndex(Request $request): Response
    {
        $this->authorize('viewAny', Company::class);
        $user = $request->user();

        $companies = Company::where('organization_id', $user->organization_id)
            ->when($request->search, fn($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->withCount(['contacts', 'opportunities'])
            ->orderBy('name')
            ->paginate(25)->withQueryString();

        return Inertia::render('Companies/Index', [
            'companies' => $companies,
            'filters' => $request->only(['search']),
            'can' => [
                'create' => $user->can('manage companies'),
                'manage' => $user->can('manage companies'),
            ],
        ]);
    }

    public function companyShow(Request $request, Company $company): Response
    {
        $this->authorize('view', $company);
        $company->load(['contacts', 'opportunities' => fn($q) => $q->latest()->limit(10)]);
        return Inertia::render('Companies/Show', [
            'company' => $company,
            'can' => ['manage' => $request->user()->can('manage companies')],
        ]);
    }

    public function companyStore(Request $request): RedirectResponse
    {
        $this->authorize('create', Company::class);
        $user = $request->user();

        $validated = $this->validateCompany($request);

        Company::create([...$validated, 'organization_id' => $user->organization_id, 'created_by' => $user->id, 'owner_id' => $user->id]);

        return redirect()->route('companies.index')->with('success', 'Company created.');
    }

    public function companyUpdate(Request $request, Company $company): RedirectResponse
    {
        $this->authorize('update', $company);

        $company->update($this->validateCompany($request));

        return back()->with('success', 'Company updated.');
    }

    public function companyDestroy(Request $request, Company $company): RedirectResponse
    {
        $this->authorize('delete', $company);

        $name = $company->name;
        $company->delete();

        return redirect()->route('companies.index')->with('success', "Company \"{$name}\" deleted.");
    }

    /** @return array<string,mixed> */
    private function validateCompany(Request $request): array
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

    // Contacts
    public function contactsIndex(Request $request): Response
    {
        $this->authorize('viewAny', Contact::class);
        $user = $request->user();

        $contacts = Contact::where('organization_id', $user->organization_id)
            ->with(['agency:id,name', 'company:id,name'])
            ->when($request->search, fn($q, $s) => $q->where('first_name', 'like', "%{$s}%")->orWhere('last_name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%"))
            ->orderBy('last_name')
            ->paginate(25)->withQueryString();

        return Inertia::render('Contacts/Index', [
            'contacts' => $contacts,
            'filters' => $request->only(['search']),
            'companies' => Company::where('organization_id', $user->organization_id)->orderBy('name')->get(['id', 'name']),
            'can' => [
                'create' => $user->can('manage contacts'),
                'manage' => $user->can('manage contacts'),
            ],
        ]);
    }

    public function contactShow(Request $request, Contact $contact): Response
    {
        $this->authorize('view', $contact);
        $user = $request->user();
        $contact->load([
            'agency:id,name',
            'company:id,name,phone,email,website',
            'followUps' => fn($q) => $q->latest('scheduled_date')->limit(8)->with('proposal:id,proposal_number,project_name'),
        ]);
        return Inertia::render('Contacts/Show', [
            'contact' => $contact,
            'companies' => Company::where('organization_id', $user->organization_id)->orderBy('name')->get(['id', 'name']),
            'can' => ['manage' => $user->can('manage contacts')],
        ]);
    }

    public function contactStore(Request $request): RedirectResponse
    {
        $this->authorize('create', Contact::class);
        $user = $request->user();

        $validated = $this->validateContact($request);

        Contact::create([...$validated, 'organization_id' => $user->organization_id, 'created_by' => $user->id, 'owner_id' => $user->id]);

        return redirect()->route('contacts.index')->with('success', 'Contact created.');
    }

    public function contactUpdate(Request $request, Contact $contact): RedirectResponse
    {
        $this->authorize('update', $contact);

        $contact->update($this->validateContact($request));

        return back()->with('success', 'Contact updated.');
    }

    public function contactDestroy(Request $request, Contact $contact): RedirectResponse
    {
        $this->authorize('delete', $contact);

        $name = trim("{$contact->first_name} {$contact->last_name}");
        $contact->delete();

        return redirect()->route('contacts.index')->with('success', "Contact \"{$name}\" deleted.");
    }

    /** @return array<string,mixed> */
    private function validateContact(Request $request): array
    {
        return $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'title' => 'nullable|string|max:150',
            'department' => 'nullable|string|max:150',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:30',
            'mobile' => 'nullable|string|max:30',
            'linkedin_url' => 'nullable|url|max:255',
            'company_id' => 'nullable|exists:companies,id',
            'is_decision_maker' => 'boolean',
            'is_key_contact' => 'boolean',
            'notes' => 'nullable|string',
        ]);
    }
}
