<?php

namespace App\Modules\Inventory\Policies;

use App\Models\User;
use App\Modules\Inventory\Models\Warehouse;

class WarehousePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view inventory');
    }

    public function view(User $user, Warehouse $warehouse): bool
    {
        return $this->sameOrg($user, $warehouse) && $user->can('view inventory');
    }

    public function create(User $user): bool
    {
        return $user->can('manage warehouses');
    }

    public function update(User $user, Warehouse $warehouse): bool
    {
        return $this->sameOrg($user, $warehouse) && $user->can('manage warehouses');
    }

    public function delete(User $user, Warehouse $warehouse): bool
    {
        return $this->sameOrg($user, $warehouse) && $user->can('manage warehouses');
    }

    private function sameOrg(User $user, Warehouse $warehouse): bool
    {
        return $user->organization_id === $warehouse->organization_id;
    }
}
