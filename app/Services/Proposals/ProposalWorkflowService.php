<?php

namespace App\Services\Proposals;

use App\Enums\ProposalStatus;
use App\Models\ProposalSubmission;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class ProposalWorkflowService
{
    private const ALLOWED_TRANSITIONS = [
        'draft' => ['in_progress', 'cancelled'],
        'in_progress' => ['under_review', 'draft', 'cancelled'],
        'under_review' => ['in_progress', 'submitted', 'cancelled'],
        'submitted' => ['pending', 'under_evaluation', 'clarification_requested', 'awarded', 'lost', 'cancelled'],
        'pending' => ['submitted', 'clarification_requested', 'awarded', 'lost', 'cancelled'],
        'under_evaluation' => ['clarification_requested', 'negotiation', 'awarded', 'lost'],
        'clarification_requested' => ['submitted', 'under_evaluation', 'lost', 'cancelled'],
        'negotiation' => ['awarded', 'lost', 'cancelled'],
        'awarded' => [],
        'lost' => [],
        'cancelled' => ['draft'],
    ];

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

        return $proposal->refresh();
    }
}
