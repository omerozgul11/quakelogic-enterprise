<?php

namespace App\Modules\Procurement\Policies;

use App\Models\User;
use App\Modules\Procurement\Models\PurchaseRequest;

class PurchaseRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view procurement');
    }

    public function view(User $user, PurchaseRequest $pr): bool
    {
        return $this->sameOrg($user, $pr) && $user->can('view procurement');
    }

    public function create(User $user): bool
    {
        return $user->can('manage purchase requests');
    }

    public function update(User $user, PurchaseRequest $pr): bool
    {
        return $this->sameOrg($user, $pr) && $user->can('manage purchase requests');
    }

    public function delete(User $user, PurchaseRequest $pr): bool
    {
        return $this->sameOrg($user, $pr) && $user->can('manage purchase requests');
    }

    public function approve(User $user, PurchaseRequest $pr): bool
    {
        return $this->sameOrg($user, $pr) && $user->can('approve purchase requests');
    }

    private function sameOrg(User $user, PurchaseRequest $pr): bool
    {
        return $user->organization_id === $pr->organization_id;
    }
}
