<?php

namespace App\Services\Proposals;

use App\Enums\ProposalStatus;
use App\Models\ProposalSubmission;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class ProposalWorkflowService
{
    public const ALLOWED_TRANSITIONS = [
        'in_progress' => ['submitted'],
        'submitted' => ['award_pending', 'awarded', 'lost'],
        'award_pending' => ['submitted', 'awarded', 'lost'],
        'awarded' => ['completed', 'submitted'],
        'completed' => ['awarded'],
        // A lost proposal can be reopened — it must never be a dead-end the user
        // can't move out of.
        'lost' => ['submitted', 'awarded', 'in_progress'],
    ];

    /**
     * The linear happy-path order used to derive the single Previous / Next
     * step buttons on the proposal header. The off-path outcome (lost) is
     * intentionally excluded — it's still reachable from the board.
     */
    public const MAIN_PIPELINE = [
        'in_progress', 'submitted',
        'award_pending', 'awarded', 'completed',
    ];

    /**
     * Resolve the one step back and one step forward from the current status,
     * restricted to valid transitions on the main pipeline.
     *
     * @return array{previous: ?array{value:string,label:string,color:string}, next: ?array{value:string,label:string,color:string}}
     */
    public function stepNavigation(ProposalStatus $current): array
    {
        $order = array_flip(self::MAIN_PIPELINE);
        $currentIndex = $order[$current->value] ?? null;
        $allowed = array_diff(self::ALLOWED_TRANSITIONS[$current->value] ?? [], ['lost']);

        $previous = null;
        $next = null;
        if ($currentIndex !== null) {
            foreach ($allowed as $value) {
                if (!isset($order[$value])) {
                    continue; // not on the main pipeline (e.g. under_evaluation)
                }
                $index = $order[$value];
                if ($index < $currentIndex && ($previous === null || $index > $order[$previous])) {
                    $previous = $value;
                } elseif ($index > $currentIndex && ($next === null || $index < $order[$next])) {
                    $next = $value;
                }
            }
        }

        $toStep = function (?string $value): ?array {
            if ($value === null) {
                return null;
            }
            $status = ProposalStatus::from($value);
            return ['value' => $status->value, 'label' => $status->label(), 'color' => $status->color()];
        };

        return ['previous' => $toStep($previous), 'next' => $toStep($next)];
    }

    /**
     * Move a proposal to a new status, recording history and applying any side
     * effects (submission/award stamping).
     *
     * When $force is true the FSM guard is skipped — the user explicitly chose a
     * status and may move to any stage. The Previous/Next step buttons still use
     * ALLOWED_TRANSITIONS for their suggestions, but an explicit status pick is
     * never blocked.
     */
    public function transition(ProposalSubmission $proposal, ProposalStatus $newStatus, User $user, ?string $notes = null, bool $force = false): ProposalSubmission
    {
        $currentStatus = $proposal->status;

        if ($currentStatus === $newStatus) {
            throw ValidationException::withMessages(['status' => 'Proposal is already in this status.']);
        }

        if (!$force) {
            $allowed = self::ALLOWED_TRANSITIONS[$currentStatus->value] ?? [];
            if (!in_array($newStatus->value, $allowed)) {
                throw ValidationException::withMessages([
                    'status' => "Cannot transition from {$currentStatus->label()} to {$newStatus->label()}.",
                ]);
            }
        }

        $proposal->update(['status' => $newStatus->value]);

        $proposal->statusHistory()->create([
            'changed_by' => $user->id,
            'from_status' => $currentStatus->value,
            'to_status' => $newStatus->value,
            'notes' => $notes,
            'changed_at' => now(),
        ]);

        // Set submission_date when submitted
        if ($newStatus === ProposalStatus::Submitted && !$proposal->submission_date) {
            $proposal->update(['submission_date' => now()->toDateString()]);
        }

        // Stamp the award when won, so earnings/dashboard metrics pick it up.
        // The value defaults to the proposal value and stays editable afterwards.
        if ($newStatus === ProposalStatus::Awarded) {
            $proposal->update(array_filter([
                'award_date' => $proposal->award_date ? null : now()->toDateString(),
                'award_value' => ((float) $proposal->award_value) > 0 ? null : $proposal->proposal_value,
            ], fn ($v) => $v !== null));

            // Phase 5: a won proposal becomes a contract. Create the linked
            // contract record (idempotent) seeded from the proposal's value so
            // the post-award financial lifecycle is ready to track.
            $this->ensureContract($proposal, $user);
        }

        return $proposal->refresh();
    }

    /**
     * Ensure a won proposal has a linked Contract record (Phase 5). Idempotent —
     * never touches an existing contract. Seeds value/currency from the proposal.
     */
    public function ensureContract(ProposalSubmission $proposal, ?User $user = null): \App\Models\Contract
    {
        return \App\Models\Contract::firstOrCreate(
            ['proposal_submission_id' => $proposal->id],
            [
                'organization_id' => $proposal->organization_id,
                'created_by' => $user?->id,
                'stage' => \App\Enums\ContractStage::ContractReview->value,
                'payment_status' => \App\Enums\PaymentStatus::NotInvoiced->value,
                'contract_value' => ((float) $proposal->award_value) > 0 ? $proposal->award_value : $proposal->proposal_value,
                'currency' => $proposal->currency ?? 'USD',
            ],
        );
    }
}
