<?php

namespace App\Http\Controllers\Web\Crm;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Contact;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ContactController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Contact::class);
        $user = $request->user();

        $contacts = Contact::where('organization_id', $user->organization_id)
            ->with(['company:id,name'])
            ->when($request->search, fn ($q, $s) => $q->where(fn ($w) => $w
                ->where('first_name', 'like', "%{$s}%")
                ->orWhere('last_name', 'like', "%{$s}%")
                ->orWhere('email', 'like', "%{$s}%")))
            ->when($request->company_id, fn ($q, $c) => $q->where('company_id', $c))
            ->orderBy('last_name')->orderBy('first_name')
            ->paginate(15)->withQueryString();

        return Inertia::render('Crm/Contacts/Index', [
            'contacts' => $contacts,
            'filters' => $request->only(['search', 'company_id']),
            'companies' => Company::where('organization_id', $user->organization_id)
                ->orderBy('name')->get(['id', 'name']),
            'can' => ['manage' => $user->can('manage contacts')],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Contact::class);
        $user = $request->user();

        $contact = Contact::create([
            ...$this->validateContact($request),
            'organization_id' => $user->organization_id,
            'created_by' => $user->id,
            'owner_id' => $user->id,
        ]);

        return back()->with('success', 'Contact created.');
    }

    public function update(Request $request, Contact $contact): RedirectResponse
    {
        $this->authorize('update', $contact);
        $contact->update($this->validateContact($request));

        return back()->with('success', 'Contact updated.');
    }

    public function destroy(Request $request, Contact $contact): RedirectResponse
    {
        $this->authorize('delete', $contact);
        $name = trim("{$contact->first_name} {$contact->last_name}");
        $contact->delete();

        return redirect()->route('crm.contacts.index')->with('success', "Contact \"{$name}\" deleted.");
    }

    /** @return array<string,mixed> */
    private function validateContact(Request $request): array
    {
        $user = $request->user();

        return $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'title' => 'nullable|string|max:150',
            'department' => 'nullable|string|max:150',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:30',
            'mobile' => 'nullable|string|max:30',
            'linkedin_url' => 'nullable|url|max:255',
            'company_id' => [
                'nullable',
                \Illuminate\Validation\Rule::exists('companies', 'id')->where('organization_id', $user->organization_id),
            ],
            'is_decision_maker' => 'boolean',
            'is_key_contact' => 'boolean',
            'notes' => 'nullable|string',
        ]);
    }
}
