<?php

namespace App\Services\Opportunities;

use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Resolves the assignment-inaction escalation ladder for opportunities that are
 * assigned but not yet actioned: 24h → owner reminder, 48h → + manager, 72h →
 * + admin, 96h → reassignment candidate (admins). Recipient resolution is reused
 * by the executive briefing. There is no per-user manager hierarchy in the app,
 * so role membership stands in for "manager"/"admin" (mirrors ProposalHealthService).
 */
class OpportunityEscalationService
{
    public const TIERS = [24, 48, 72, 96];

    /** The highest tier whose hour threshold has elapsed (0 if under 24h). */
    public function tierFor(int $hours): int
    {
        $tier = 0;
        foreach (self::TIERS as $t) {
            if ($hours >= $t) {
                $tier = $t;
            }
        }

        return $tier;
    }

    /**
     * Who hears about a given tier. 24 = the owner only; 48 adds managers; 72
     * and 96 add admins (96 = reassignment candidate).
     *
     * @return Collection<int,User>
     */
    public function recipientsForTier(Opportunity $opportunity, int $tier): Collection
    {
        $recipients = collect();

        if ($owner = ($opportunity->owner_id ? User::find($opportunity->owner_id) : null)) {
            $recipients->push($owner);
        }
        if ($tier >= 48) {
            $recipients = $recipients->merge($this->managers($opportunity->organization_id));
        }
        if ($tier >= 72) {
            $recipients = $recipients->merge($this->admins($opportunity->organization_id));
        }

        return $recipients->filter(fn (User $u) => (bool) $u->is_active)->unique('id')->values();
    }

    /** @return Collection<int,User> */
    public function managers(int $organizationId): Collection
    {
        return User::where('organization_id', $organizationId)
            ->where('is_active', true)
            ->role(['Business Development Manager', 'CEO'])
            ->get();
    }

    /** @return Collection<int,User> */
    public function admins(int $organizationId): Collection
    {
        return User::where('organization_id', $organizationId)
            ->where('is_active', true)
            ->role(['Super Admin', 'CEO'])
            ->get();
    }
}
