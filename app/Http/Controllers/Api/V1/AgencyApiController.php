<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgencyApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $agencies = Agency::where('organization_id', $request->user()->organization_id)
            ->when($request->search, fn($q) => $q->where('name', 'like', '%' . $request->search . '%'))
            ->orderBy('name')
            ->paginate($request->integer('per_page', 25));

        return response()->json($agencies);
    }

    public function show(Request $request, Agency $agency): JsonResponse
    {
        abort_unless($agency->organization_id === $request->user()->organization_id, 403);
        return response()->json($agency->load('contacts'));
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Agency::class);
        $validated = $request->validate(['name' => 'required|string|max:255', 'acronym' => 'nullable|string|max:20']);
        $agency = Agency::create([...$validated, 'organization_id' => $request->user()->organization_id, 'ulid' => (string) \Illuminate\Support\Str::ulid()]);
        return response()->json($agency, 201);
    }

    public function update(Request $request, Agency $agency): JsonResponse
    {
        abort_unless($agency->organization_id === $request->user()->organization_id, 403);
        $validated = $request->validate(['name' => 'sometimes|string|max:255', 'acronym' => 'nullable|string|max:20']);
        $agency->update($validated);
        return response()->json($agency);
    }

    public function destroy(Request $request, Agency $agency): JsonResponse
    {
        abort_unless($agency->organization_id === $request->user()->organization_id, 403);
        $agency->delete();
        return response()->json(['message' => 'Deleted']);
    }
}
