<?php

namespace App\Services\Proposals;

use App\Enums\ProposalStatus;
use App\Models\ProposalSubmission;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class ProposalWorkflowService
{
    public const ALLOWED_TRANSITIONS = [
        'draft' => ['in_progress', 'cancelled'],
        'in_progress' => ['under_review', 'draft', 'cancelled'],
        'under_review' => ['in_progress', 'submitted', 'cancelled'],
        'submitted' => ['pending', 'under_evaluation', 'clarification_requested', 'awarded', 'lost', 'cancelled'],
        'pending' => ['submitted', 'clarification_requested', 'awarded', 'lost', 'cancelled'],
        'under_evaluation' => ['clarification_requested', 'negotiation', 'awarded', 'lost'],
        'clarification_requested' => ['submitted', 'under_evaluation', 'lost', 'cancelled'],
        'negotiation' => ['awarded', 'lost', 'cancelled'],
        'awarded' => ['completed', 'submitted'],
        'completed' => ['awarded'],
        // A lost or cancelled proposal can be reopened — it must never be a
        // dead-end the user can't move out of.
        'lost' => ['submitted', 'awarded', 'draft'],
        'cancelled' => ['draft', 'in_progress'],
    ];

    /**
     * The linear happy-path order used to derive the single Previous / Next
     * step buttons on the proposal header. Off-path outcomes (lost, cancelled,
     * and the legacy under_evaluation) are intentionally excluded — they're
     * still reachable from the board.
     */
    public const MAIN_PIPELINE = [
        'draft', 'in_progress', 'under_review', 'submitted',
        'pending', 'clarification_requested', 'negotiation', 'awarded', 'completed',
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
        $allowed = array_diff(self::ALLOWED_TRANSITIONS[$current->value] ?? [], ['lost', 'cancelled']);

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

    public function transition(ProposalSubmission $proposal, ProposalStatus $newStatus, User $user, ?string $notes = null): ProposalSubmission
    {
        $currentStatus = $proposal->status;

        if ($currentStatus === $newStatus) {
            throw ValidationException::withMessages(['status' => 'Proposal is already in this status.']);
        }

        $allowed = self::ALLOWED_TRANSITIONS[$currentStatus->value] ?? [];
        if (!in_array($newStatus->value, $allowed)) {
            throw ValidationException::withMessages([
                'status' => "Cannot transition from {$currentStatus->label()} to {$newStatus->label()}.",
            ]);
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
        }

        return $proposal->refresh();
    }
}
