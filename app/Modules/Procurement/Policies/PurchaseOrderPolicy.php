<?php

namespace App\Modules\Procurement\Policies;

use App\Models\User;
use App\Modules\Procurement\Models\PurchaseOrder;

class PurchaseOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view procurement');
    }

    public function view(User $user, PurchaseOrder $po): bool
    {
        return $this->sameOrg($user, $po) && $user->can('view procurement');
    }

    public function create(User $user): bool
    {
        return $user->can('manage purchase orders');
    }

    public function update(User $user, PurchaseOrder $po): bool
    {
        return $this->sameOrg($user, $po) && $user->can('manage purchase orders');
    }

    public function delete(User $user, PurchaseOrder $po): bool
    {
        return $this->sameOrg($user, $po) && $user->can('manage purchase orders');
    }

    public function approve(User $user, PurchaseOrder $po): bool
    {
        return $this->sameOrg($user, $po) && $user->can('approve purchase orders');
    }

    public function receive(User $user, PurchaseOrder $po): bool
    {
        return $this->sameOrg($user, $po) && $user->can('receive goods');
    }

    private function sameOrg(User $user, PurchaseOrder $po): bool
    {
        return $user->organization_id === $po->organization_id;
    }
}
