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

        // Users with 'view all proposals' can see any proposal
        if ($user->can('view all proposals')) return true;

        // Users can see their own proposals (owner/team member/manager)
        if ($user->can('view proposals')) {
            return $proposal->owner_id === $user->id
                || $proposal->proposal_manager_id === $user->id
                || $proposal->teamMembers()->where('user_id', $user->id)->exists();
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

        // Restrict writers to their assigned proposals
        if ($user->hasRole('Proposal Writer')) {
            return $proposal->teamMembers()->where('user_id', $user->id)->exists()
                || $proposal->owner_id === $user->id;
        }

        return true;
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
