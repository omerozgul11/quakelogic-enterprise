<?php

namespace App\Policies;

use App\Models\ProposalMailing;
use App\Models\User;

/**
 * Tenant isolation + permission, mirroring Proposals' policy convention:
 * a user may only touch mailings in their own organization, and writes require
 * the `manage mailings` permission.
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
        return $user->can('manage mailings');
    }

    public function update(User $user, ProposalMailing $mailing): bool
    {
        return $this->isSameOrg($user, $mailing) && $user->can('manage mailings');
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
