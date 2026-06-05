<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContactApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $contacts = Contact::where('organization_id', $request->user()->organization_id)
            ->when($request->search, fn($q) => $q->where(fn($q2) => $q2
                ->where('first_name', 'like', '%' . $request->search . '%')
                ->orWhere('last_name', 'like', '%' . $request->search . '%')
                ->orWhere('email', 'like', '%' . $request->search . '%')))
            ->with(['agency:id,name', 'company:id,name'])
            ->orderBy('last_name')
            ->paginate($request->integer('per_page', 25));

        return response()->json($contacts);
    }

    public function show(Request $request, Contact $contact): JsonResponse
    {
        abort_unless($contact->organization_id === $request->user()->organization_id, 403);
        return response()->json($contact->load(['agency', 'company']));
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Contact::class);
        $validated = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'nullable|email',
            'phone' => 'nullable|string|max:30',
            'agency_id' => 'nullable|exists:agencies,id',
            'company_id' => 'nullable|exists:companies,id',
        ]);
        $contact = Contact::create([...$validated, 'organization_id' => $request->user()->organization_id, 'ulid' => (string) \Illuminate\Support\Str::ulid()]);
        return response()->json($contact, 201);
    }

    public function update(Request $request, Contact $contact): JsonResponse
    {
        abort_unless($contact->organization_id === $request->user()->organization_id, 403);
        $validated = $request->validate([
            'first_name' => 'sometimes|string|max:100',
            'last_name' => 'sometimes|string|max:100',
            'email' => 'nullable|email',
        ]);
        $contact->update($validated);
        return response()->json($contact);
    }

    public function destroy(Request $request, Contact $contact): JsonResponse
    {
        abort_unless($contact->organization_id === $request->user()->organization_id, 403);
        $contact->delete();
        return response()->json(['message' => 'Deleted']);
    }
}
