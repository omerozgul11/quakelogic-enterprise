<?php

namespace App\Http\Controllers\Web;

use App\Enums\OpportunityPriority;
use App\Http\Controllers\Controller;
use App\Models\OpportunityKeywordGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin editor for opportunity keyword groups — the editable source of truth for
 * relevance scoring. Gated by the admin route group (role:Super Admin).
 */
class OpportunityKeywordGroupController extends Controller
{
    public function index(Request $request): Response
    {
        $orgId = $request->user()->organization_id;

        $groups = OpportunityKeywordGroup::forOrganization($orgId)
            ->orderBy('sort_order')->orderBy('name')->get()
            ->map(fn (OpportunityKeywordGroup $g) => [
                'id' => $g->id,
                'name' => $g->name,
                'keywords' => $g->keywords ?? [],
                'naics_codes' => $g->naics_codes ?? [],
                'weight' => $g->weight,
                'is_exclusion' => $g->is_exclusion,
                'is_active' => $g->is_active,
                'color' => $g->color,
            ])->values();

        return Inertia::render('Admin/KeywordGroups', [
            'groups' => $groups,
            'priorities' => OpportunityPriority::options(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateGroup($request);
        OpportunityKeywordGroup::create([
            ...$data,
            'organization_id' => $request->user()->organization_id,
            'created_by' => $request->user()->id,
            'sort_order' => (int) OpportunityKeywordGroup::forOrganization($request->user()->organization_id)->max('sort_order') + 1,
        ]);

        return back()->with('success', 'Keyword group created.');
    }

    public function update(Request $request, OpportunityKeywordGroup $keywordGroup): RedirectResponse
    {
        abort_unless($keywordGroup->organization_id === $request->user()->organization_id, 404);
        $keywordGroup->update($this->validateGroup($request));

        return back()->with('success', 'Keyword group updated.');
    }

    public function destroy(Request $request, OpportunityKeywordGroup $keywordGroup): RedirectResponse
    {
        abort_unless($keywordGroup->organization_id === $request->user()->organization_id, 404);
        $keywordGroup->delete();

        return back()->with('success', 'Keyword group removed.');
    }

    /** @return array<string,mixed> */
    private function validateGroup(Request $request): array
    {
        $v = $request->validate([
            'name' => 'required|string|max:255',
            'keywords' => 'nullable|array',
            'keywords.*' => 'string|max:100',
            'naics_codes' => 'nullable|array',
            'naics_codes.*' => 'string|max:10',
            'weight' => 'nullable|integer|min:1|max:100',
            'is_exclusion' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'color' => 'nullable|string|max:30',
        ]);

        return [
            'name' => $v['name'],
            'keywords' => array_values(array_filter(array_map('trim', $v['keywords'] ?? []), fn ($k) => $k !== '')),
            'naics_codes' => array_values(array_filter(array_map('trim', $v['naics_codes'] ?? []), fn ($k) => $k !== '')),
            'weight' => $v['weight'] ?? 10,
            'is_exclusion' => (bool) ($v['is_exclusion'] ?? false),
            'is_active' => (bool) ($v['is_active'] ?? true),
            'color' => $v['color'] ?? null,
        ];
    }
}
