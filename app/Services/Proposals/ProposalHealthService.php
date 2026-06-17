<?php

namespace App\Services\Proposals;

use App\Models\ProposalSubmission;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Phase 2 — Proposal Health & Follow-Up Control.
 *
 * Turns "days since the last logged client contact" into a traffic-light health
 * signal and the no-contact escalation ladder. Health applies only to active
 * proposals (in-flight work); finished work (awarded/completed/lost/cancelled)
 * has no health.
 */
class ProposalHealthService
{
    /** Health bands (inclusive lower bound, in days since last client contact). */
    public const GREEN_MAX = 14;   // 0–14   green
    public const YELLOW_MAX = 30;  // 15–30  yellow
    public const ORANGE_MAX = 45;  // 31–45  orange
    //                              46+      red

    /** Escalation ladder: notify owner at 30d, +manager at 45d, +manager+admin at 60d. */
    public const TIER_OWNER = 30;
    public const TIER_MANAGER = 45;
    public const TIER_ADMIN = 60;

    /**
     * The clock for "last client contact". Falls back to when the proposal was
     * created so a brand-new proposal starts green and ages if nobody logs
     * contact, rather than being treated as "never contacted = max risk".
     */
    public function lastContactAt(ProposalSubmission $proposal): Carbon
    {
        return $proposal->last_client_contact_at ?? $proposal->created_at ?? now();
    }

    public function daysSinceContact(ProposalSubmission $proposal): int
    {
        return (int) $this->lastContactAt($proposal)->startOfDay()->diffInDays(now()->startOfDay());
    }

    /** Whether health tracking applies (only to active, in-flight proposals). */
    public function tracksHealth(ProposalSubmission $proposal): bool
    {
        return $proposal->status->isActive();
    }

    /**
     * Health summary for display, or null when health does not apply (finished
     * proposals). color ∈ green|yellow|orange|red.
     *
     * @return array{color:string,days:int,label:string}|null
     */
    public function health(ProposalSubmission $proposal): ?array
    {
        if (!$this->tracksHealth($proposal)) {
            return null;
        }

        $days = $this->daysSinceContact($proposal);
        $color = match (true) {
            $days <= self::GREEN_MAX => 'green',
            $days <= self::YELLOW_MAX => 'yellow',
            $days <= self::ORANGE_MAX => 'orange',
            default => 'red',
        };

        return [
            'color' => $color,
            'days' => $days,
            'label' => $days === 0 ? 'Contacted today' : "{$days}d since client contact",
        ];
    }

    /**
     * The escalation tier the proposal currently warrants (0/30/45/60), based on
     * days since contact. 0 means no escalation yet.
     */
    public function escalationTier(ProposalSubmission $proposal): int
    {
        if (!$this->tracksHealth($proposal)) {
            return 0;
        }

        $days = $this->daysSinceContact($proposal);

        return match (true) {
            $days >= self::TIER_ADMIN => self::TIER_ADMIN,
            $days >= self::TIER_MANAGER => self::TIER_MANAGER,
            $days >= self::TIER_OWNER => self::TIER_OWNER,
            default => 0,
        };
    }

    /**
     * Active users who should be alerted for a given escalation tier:
     *  - 30 → owner
     *  - 45 → owner + manager
     *  - 60 → owner + manager + admins
     *
     * Manager = the proposal manager if set, else org Business Development
     * Managers / CEOs. Admin = org Super Admins / CEOs. The app has no
     * per-user manager hierarchy, so role membership stands in for it.
     *
     * @return Collection<int,User>
     */
    public function recipientsForTier(ProposalSubmission $proposal, int $tier): Collection
    {
        $ids = collect([$proposal->owner_id, $proposal->created_by]);

        if ($tier >= self::TIER_MANAGER) {
            $ids->push($proposal->proposal_manager_id);
            if (!$proposal->proposal_manager_id) {
                $ids = $ids->merge($this->orgUserIdsWithRole($proposal->organization_id, ['Business Development Manager', 'CEO']));
            }
        }

        if ($tier >= self::TIER_ADMIN) {
            $ids = $ids->merge($this->orgUserIdsWithRole($proposal->organization_id, ['Super Admin', 'CEO']));
        }

        $ids = $ids->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return collect();
        }

        return User::whereIn('id', $ids)
            ->where('organization_id', $proposal->organization_id)
            ->where('is_active', true)
            ->get();
    }

    /** @return Collection<int,int> */
    private function orgUserIdsWithRole(int $organizationId, array $roles): Collection
    {
        return User::where('organization_id', $organizationId)
            ->whereHas('roles', fn ($q) => $q->whereIn('name', $roles))
            ->pluck('id');
    }
}
