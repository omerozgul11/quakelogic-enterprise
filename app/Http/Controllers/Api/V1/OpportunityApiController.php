<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Opportunity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OpportunityApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $opportunities = Opportunity::where('organization_id', $request->user()->organization_id)
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->search, fn($q) => $q->where(fn($q2) => $q2
                ->where('title', 'like', '%' . $request->search . '%')
                ->orWhere('solicitation_number', 'like', '%' . $request->search . '%')))
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 25));

        return response()->json($opportunities);
    }

    public function show(Request $request, Opportunity $opportunity): JsonResponse
    {
        abort_unless($opportunity->organization_id === $request->user()->organization_id, 403);
        return response()->json($opportunity->load(['capturePlan', 'proposals']));
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Opportunity::class);
        $validated = $request->validate([
            'title' => 'required|string|max:500',
            'source' => 'required|string',
            'status' => 'required|string',
            'agency_name' => 'nullable|string|max:255',
            'estimated_value' => 'nullable|numeric',
            'due_date' => 'nullable|date',
        ]);

        $opportunity = Opportunity::create([
            ...$validated,
            'organization_id' => $request->user()->organization_id,
            'ulid' => (string) \Illuminate\Support\Str::ulid(),
            'canonical_hash' => hash('sha256', ($validated['solicitation_number'] ?? '') . ($validated['title'] ?? '')),
        ]);

        return response()->json($opportunity, 201);
    }

    public function update(Request $request, Opportunity $opportunity): JsonResponse
    {
        abort_unless($opportunity->organization_id === $request->user()->organization_id, 403);
        $this->authorize('update', $opportunity);

        $validated = $request->validate([
            'title' => 'sometimes|string|max:500',
            'status' => 'sometimes|string',
            'estimated_value' => 'nullable|numeric',
            'due_date' => 'nullable|date',
        ]);

        $opportunity->update($validated);
        return response()->json($opportunity);
    }

    public function destroy(Request $request, Opportunity $opportunity): JsonResponse
    {
        abort_unless($opportunity->organization_id === $request->user()->organization_id, 403);
        $this->authorize('delete', $opportunity);
        $opportunity->delete();
        return response()->json(['message' => 'Deleted']);
    }
}
