<?php

namespace App\Modules\Manufacturing\Policies;

use App\Models\User;
use App\Modules\Manufacturing\Models\WorkOrder;

class WorkOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view manufacturing');
    }

    public function view(User $user, WorkOrder $wo): bool
    {
        return $this->sameOrg($user, $wo) && $user->can('view manufacturing');
    }

    public function create(User $user): bool
    {
        return $user->can('manage work orders');
    }

    public function update(User $user, WorkOrder $wo): bool
    {
        return $this->sameOrg($user, $wo) && $user->can('manage work orders');
    }

    public function delete(User $user, WorkOrder $wo): bool
    {
        return $this->sameOrg($user, $wo) && $user->can('manage work orders');
    }

    public function complete(User $user, WorkOrder $wo): bool
    {
        return $this->sameOrg($user, $wo) && $user->can('complete work orders');
    }

    private function sameOrg(User $user, WorkOrder $wo): bool
    {
        return $user->organization_id === $wo->organization_id;
    }
}
