<?php

namespace App\Http\Controllers\Web;

use App\Enums\CostCategory;
use App\Http\Controllers\Controller;
use App\Models\ProposalCost;
use App\Models\ProposalSubmission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Cost line items attached to a proposal, used for the quick profit-margin
 * estimate (bid vs. cost). Gated on the same `update` ability as the proposal.
 */
class ProposalCostController extends Controller
{
    public function store(Request $request, ProposalSubmission $proposalSubmission): RedirectResponse
    {
        $this->authorize('update', $proposalSubmission);

        $validated = $request->validate([
            'description' => 'required|string|max:255',
            'category' => ['nullable', Rule::in($this->categoryValues())],
            'amount' => 'required|numeric|min:0',
        ]);

        $proposalSubmission->costs()->create([
            'organization_id' => $proposalSubmission->organization_id,
            'created_by' => $request->user()->id,
            'description' => $validated['description'],
            'category' => $validated['category'] ?? CostCategory::Other->value,
            'amount' => $validated['amount'],
            'sort_order' => (int) $proposalSubmission->costs()->max('sort_order') + 1,
        ]);

        return back()->with('success', 'Cost line added.');
    }

    public function update(Request $request, ProposalSubmission $proposalSubmission, ProposalCost $cost): RedirectResponse
    {
        $this->authorize('update', $proposalSubmission);
        abort_unless($cost->proposal_submission_id === $proposalSubmission->id, 404);

        $validated = $request->validate([
            'description' => 'sometimes|required|string|max:255',
            'category' => ['sometimes', Rule::in($this->categoryValues())],
            'amount' => 'sometimes|required|numeric|min:0',
        ]);

        $cost->update($validated);

        return back()->with('success', 'Cost line updated.');
    }

    public function destroy(Request $request, ProposalSubmission $proposalSubmission, ProposalCost $cost): RedirectResponse
    {
        $this->authorize('update', $proposalSubmission);
        abort_unless($cost->proposal_submission_id === $proposalSubmission->id, 404);

        $cost->delete();

        return back()->with('success', 'Cost line removed.');
    }

    /** @return list<string> */
    private function categoryValues(): array
    {
        return array_map(fn (CostCategory $c) => $c->value, CostCategory::cases());
    }
}
