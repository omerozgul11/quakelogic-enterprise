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
        if (!$this->isSameOrg($user, $proposal)) return false;

        // Admins (holders of "view all proposals") see every proposal.
        if ($user->can('view all proposals')) return true;

        // Everyone else sees only proposals they're involved in: the creator,
        // the owner, the manager, or an attached team member.
        if ($user->can('view proposals')) {
            return $this->isInvolved($user, $proposal);
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->can('create proposals');
    }

    public function update(User $user, ProposalSubmission $proposal): bool
    {
        if (!$this->isSameOrg($user, $proposal)) return false;
        if (!$user->can('update proposals')) return false;

        // Admins may edit any proposal in their organization (they also assign
        // ownership). Everyone else may edit only the proposals they own or are
        // an attached team member of — others are read-only.
        if ($user->hasRole('Super Admin')) return true;

        return $proposal->owner_id === $user->id
            || $proposal->proposal_manager_id === $user->id
            || $proposal->teamMembers()->where('user_id', $user->id)->exists();
    }

    /**
     * Whether the user is involved with the proposal in any capacity — creator,
     * owner, manager, or team member. Drives who can see a proposal.
     */
    private function isInvolved(User $user, ProposalSubmission $proposal): bool
    {
        return $proposal->created_by === $user->id
            || $proposal->owner_id === $user->id
            || $proposal->proposal_manager_id === $user->id
            || $proposal->teamMembers()->where('user_id', $user->id)->exists();
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
