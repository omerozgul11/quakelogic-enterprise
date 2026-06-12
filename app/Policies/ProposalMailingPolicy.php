<?php

namespace App\Policies;

use App\Models\ProposalMailing;
use App\Models\User;

/**
 * Tenant isolation + a single access gate: anyone with `access shipments` (set
 * per-user from the Shipments admin panel, independent of their proposal role)
 * gets full use of the Shipments section within their own organization.
 */
class ProposalMailingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('access shipments');
    }

    public function view(User $user, ProposalMailing $mailing): bool
    {
        return $this->isSameOrg($user, $mailing) && $user->can('access shipments');
    }

    public function create(User $user): bool
    {
        return $user->can('access shipments');
    }

    public function update(User $user, ProposalMailing $mailing): bool
    {
        return $this->isSameOrg($user, $mailing) && $user->can('access shipments');
    }

    public function delete(User $user, ProposalMailing $mailing): bool
    {
        return $this->update($user, $mailing);
    }

    private function isSameOrg(User $user, ProposalMailing $mailing): bool
    {
        return $user->organization_id !== null
            && $user->organization_id === $mailing->organization_id;
    }
}
