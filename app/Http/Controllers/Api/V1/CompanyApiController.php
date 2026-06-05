<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $companies = Company::where('organization_id', $request->user()->organization_id)
            ->when($request->search, fn($q) => $q->where('name', 'like', '%' . $request->search . '%'))
            ->orderBy('name')
            ->paginate($request->integer('per_page', 25));

        return response()->json($companies);
    }

    public function show(Request $request, Company $company): JsonResponse
    {
        abort_unless($company->organization_id === $request->user()->organization_id, 403);
        return response()->json($company->load('contacts'));
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Company::class);
        $validated = $request->validate(['name' => 'required|string|max:255', 'type' => 'nullable|string']);
        $company = Company::create([...$validated, 'organization_id' => $request->user()->organization_id, 'ulid' => (string) \Illuminate\Support\Str::ulid()]);
        return response()->json($company, 201);
    }

    public function update(Request $request, Company $company): JsonResponse
    {
        abort_unless($company->organization_id === $request->user()->organization_id, 403);
        $validated = $request->validate(['name' => 'sometimes|string|max:255', 'type' => 'nullable|string']);
        $company->update($validated);
        return response()->json($company);
    }

    public function destroy(Request $request, Company $company): JsonResponse
    {
        abort_unless($company->organization_id === $request->user()->organization_id, 403);
        $company->delete();
        return response()->json(['message' => 'Deleted']);
    }
}
