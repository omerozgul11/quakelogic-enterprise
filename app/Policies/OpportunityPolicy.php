<?php

namespace App\Policies;

use App\Models\Opportunity;
use App\Models\User;

class OpportunityPolicy
{
    private function isSameOrg(User $user, Opportunity $opportunity): bool
    {
        return $user->organization_id === $opportunity->organization_id;
    }

    public function viewAny(User $user): bool
    {
        return $user->can('view opportunities');
    }

    public function view(User $user, Opportunity $opportunity): bool
    {
        return $this->isSameOrg($user, $opportunity) && $user->can('view opportunities');
    }

    public function create(User $user): bool
    {
        return $user->can('create opportunities');
    }

    public function update(User $user, Opportunity $opportunity): bool
    {
        return $this->isSameOrg($user, $opportunity) && $user->can('update opportunities');
    }

    public function delete(User $user, Opportunity $opportunity): bool
    {
        return $this->isSameOrg($user, $opportunity) && $user->can('delete opportunities');
    }

    public function import(User $user): bool
    {
        return $user->can('import opportunities');
    }

    public function makeGoNoGoDecision(User $user, Opportunity $opportunity): bool
    {
        return $this->isSameOrg($user, $opportunity) && $user->can('make go no go decision');
    }
}
