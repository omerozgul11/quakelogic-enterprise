<?php

namespace App\Policies;

use App\Models\ProposalSubmission;
use App\Models\User;

class ProposalSubmissionPolicy
{
    private function isSameOrg(User $user, ProposalSubmission $proposal): bool
    {
        return $user->organization_id === $proposal->organization_id;
    }

    public function viewAny(User $user): bool
    {
        return $user->can('view proposals');
    }

    public function view(User $user, ProposalSubmission $proposal): bool
    {
        // Every user in the organization can view every proposal (so anyone can
        // open and edit any of them).
        return $this->isSameOrg($user, $proposal) && $user->can('view proposals');
    }

    public function create(User $user): bool
    {
        return $user->can('create proposals');
    }

    public function update(User $user, ProposalSubmission $proposal): bool
    {
        // Every user in the organization may edit every aspect of any proposal
        // (collaborative editing) — gated only by org scope + the permission.
        return $this->isSameOrg($user, $proposal) && $user->can('update proposals');
    }

    public function delete(User $user, ProposalSubmission $proposal): bool
    {
        return $this->isSameOrg($user, $proposal) && $user->can('delete proposals');
    }

    public function submit(User $user, ProposalSubmission $proposal): bool
    {
        return $this->isSameOrg($user, $proposal) && $user->can('submit proposals');
    }

    public function approve(User $user, ProposalSubmission $proposal): bool
    {
        return $this->isSameOrg($user, $proposal) && $user->can('approve proposals');
    }

    public function viewPrivateDetails(User $user, ProposalSubmission $proposal): bool
    {
        if (!$this->isSameOrg($user, $proposal)) return false;
        return $user->can('view private proposal details');
    }
}
