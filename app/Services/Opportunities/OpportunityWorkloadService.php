<?php

namespace App\Services\Opportunities;

use App\Enums\OpportunityStatus;
use App\Enums\ProposalStatus;
use App\Models\Opportunity;
use App\Models\ProposalSubmission;
use App\Models\User;

/**
 * Computes and caches each user's "active workload score" — the count of live
 * responsibilities they carry (open opportunities they own or are assigned, plus
 * active proposals they own). The AI assignment engine uses it to balance load,
 * and the executive dashboard surfaces highest/lowest-loaded users from it.
 *
 * The score is cached on `users.workload_score` (with `workload_updated_at`) so
 * dashboards don't recompute per request; recompute on assignment changes and
 * nightly.
 */
class OpportunityWorkloadService
{
    /** Recompute and persist a single user's workload score; returns the score. */
    public function recompute(User $user): int
    {
        $orgId = $user->organization_id;

        $closedOppStatuses = array_map(
            fn (OpportunityStatus $s) => $s->value,
            array_filter(OpportunityStatus::cases(), fn (OpportunityStatus $s) => $s->isClosed()),
        );

        $openOpportunities = Opportunity::forOrganization($orgId)
            ->whereNotIn('status', $closedOppStatuses)
            ->where(fn ($q) => $q->where('owner_id', $user->id)->orWhere('assigned_to', $user->id))
            ->count();

        $activeProposalStatuses = array_map(
            fn (ProposalStatus $s) => $s->value,
            array_filter(ProposalStatus::cases(), fn (ProposalStatus $s) => $s->isActive()),
        );

        $activeProposals = ProposalSubmission::forOrganization($orgId)
            ->where('owner_id', $user->id)
            ->whereIn('status', $activeProposalStatuses)
            ->count();

        $score = $openOpportunities + $activeProposals;

        $user->forceFill([
            'workload_score' => $score,
            'workload_updated_at' => now(),
        ])->saveQuietly();

        return $score;
    }

    /** Recompute every active user in an organization. Returns [userId => score]. */
    public function recomputeForOrganization(int $organizationId): array
    {
        $scores = [];
        User::where('organization_id', $organizationId)
            ->where('is_active', true)
            ->each(function (User $user) use (&$scores) {
                $scores[$user->id] = $this->recompute($user);
            });

        return $scores;
    }
}
