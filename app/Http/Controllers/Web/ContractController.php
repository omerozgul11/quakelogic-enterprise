<?php

namespace App\Http\Controllers\Web;

use App\Enums\ContractStage;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\DeliveryMilestone;
use App\Models\ProposalSubmission;
use App\Support\Currency;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Phase 5 — Contract & Financial Lifecycle. Contracts are the post-award
 * financial record for a won proposal; this controller drives the org-wide
 * contracts overview, the per-proposal contract panel, and delivery milestones.
 */
class ContractController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $contracts = Contract::forOrganization($user->organization_id)
            ->with(['proposal:id,proposal_number,project_name,owner_id,company_id', 'proposal.owner:id,name', 'proposal.company:id,name'])
            ->withCount(['milestones', 'milestones as completed_milestones_count' => fn ($q) => $q->whereNotNull('completed_at')])
            ->orderByDesc('updated_at')
            ->get();

        $rows = $contracts->map(fn (Contract $c) => [
            'id' => $c->id,
            'proposal_id' => $c->proposal_submission_id,
            'proposal_number' => $c->proposal?->proposal_number,
            'project_name' => $c->proposal?->project_name,
            'company' => $c->proposal?->company?->name,
            'owner' => $c->proposal?->owner?->name,
            'contract_number' => $c->contract_number,
            'po_number' => $c->po_number,
            'invoice_number' => $c->invoice_number,
            'stage' => $c->stage->value,
            'stage_label' => $c->stage->label(),
            'stage_color' => $c->stage->color(),
            'payment_status' => $c->payment_status->value,
            'payment_label' => $c->payment_status->label(),
            'payment_color' => $c->payment_status->color(),
            'contract_value' => (float) $c->contract_value,
            'amount_paid' => (float) $c->amount_paid,
            'currency' => $c->currency ?? Currency::DEFAULT,
            'milestones' => (int) $c->milestones_count,
            'milestones_done' => (int) $c->completed_milestones_count,
        ])->values();

        // Financial roll-up, normalised to USD across currencies.
        $totals = [
            'count' => $contracts->count(),
            'value' => $contracts->reduce(fn ($c, $x) => $c + Currency::toUsd((float) $x->contract_value, $x->currency), 0.0),
            'invoiced' => $contracts->reduce(fn ($c, $x) => $c + Currency::toUsd((float) $x->amount_invoiced, $x->currency), 0.0),
            'paid' => $contracts->reduce(fn ($c, $x) => $c + Currency::toUsd((float) $x->amount_paid, $x->currency), 0.0),
        ];
        $totals['outstanding'] = max(0, $totals['value'] - $totals['paid']);

        return Inertia::render('Contracts/Index', [
            'contracts' => $rows,
            'totals' => $totals,
            'stages' => $this->stageOptions(),
            'paymentStatuses' => $this->paymentOptions(),
        ]);
    }

    /** Create or update the contract attached to a proposal. */
    public function upsert(Request $request, ProposalSubmission $proposalSubmission): RedirectResponse
    {
        $this->authorize('update', $proposalSubmission);

        $validated = $request->validate([
            'contract_number' => 'nullable|string|max:100',
            'po_number' => 'nullable|string|max:100',
            'invoice_number' => 'nullable|string|max:100',
            'stage' => ['required', Rule::in(array_map(fn ($s) => $s->value, ContractStage::cases()))],
            'payment_status' => ['required', Rule::in(array_map(fn ($s) => $s->value, PaymentStatus::cases()))],
            'contract_value' => 'nullable|numeric|min:0',
            'amount_invoiced' => 'nullable|numeric|min:0',
            'amount_paid' => 'nullable|numeric|min:0',
            'currency' => ['nullable', Rule::in(Currency::codes())],
            'signed_at' => 'nullable|date',
            'po_received_at' => 'nullable|date',
            'invoice_sent_at' => 'nullable|date',
            'paid_at' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        $user = $request->user();
        $validated['currency'] = Currency::normalize($validated['currency'] ?? $proposalSubmission->currency);

        $proposalSubmission->contract()->updateOrCreate(
            ['proposal_submission_id' => $proposalSubmission->id],
            array_merge($validated, [
                'organization_id' => $proposalSubmission->organization_id,
                'created_by' => $proposalSubmission->contract?->created_by ?? $user->id,
            ])
        );

        return back()->with('success', 'Contract details saved.');
    }

    public function storeMilestone(Request $request, Contract $contract): RedirectResponse
    {
        $this->authorizeContract($contract);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'due_date' => 'nullable|date',
        ]);

        $contract->milestones()->create([
            'title' => $validated['title'],
            'due_date' => $validated['due_date'] ?? null,
            'sort_order' => (int) $contract->milestones()->max('sort_order') + 1,
        ]);

        return back()->with('success', 'Milestone added.');
    }

    public function updateMilestone(Request $request, Contract $contract, DeliveryMilestone $milestone): RedirectResponse
    {
        $this->authorizeContract($contract);
        abort_unless($milestone->contract_id === $contract->id, 404);

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'due_date' => 'nullable|date',
            'completed' => 'sometimes|boolean',
        ]);

        if (array_key_exists('completed', $validated)) {
            $milestone->completed_at = $validated['completed'] ? now()->toDateString() : null;
        }
        if (array_key_exists('title', $validated)) {
            $milestone->title = $validated['title'];
        }
        if (array_key_exists('due_date', $validated)) {
            $milestone->due_date = $validated['due_date'];
        }
        $milestone->save();

        return back()->with('success', 'Milestone updated.');
    }

    public function destroyMilestone(Request $request, Contract $contract, DeliveryMilestone $milestone): RedirectResponse
    {
        $this->authorizeContract($contract);
        abort_unless($milestone->contract_id === $contract->id, 404);

        $milestone->delete();

        return back()->with('success', 'Milestone removed.');
    }

    private function authorizeContract(Contract $contract): void
    {
        $proposal = $contract->proposal;
        abort_if($proposal === null, 404);
        $this->authorize('update', $proposal);
    }

    /** @return array<int,array{value:string,label:string,color:string}> */
    public function stageOptions(): array
    {
        return array_map(fn (ContractStage $s) => ['value' => $s->value, 'label' => $s->label(), 'color' => $s->color()], ContractStage::cases());
    }

    /** @return array<int,array{value:string,label:string,color:string}> */
    public function paymentOptions(): array
    {
        return array_map(fn (PaymentStatus $s) => ['value' => $s->value, 'label' => $s->label(), 'color' => $s->color()], PaymentStatus::cases());
    }
}
