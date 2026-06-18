<?php

namespace App\Modules\Procurement\Policies;

use App\Models\User;
use App\Modules\Procurement\Models\Supplier;

class SupplierPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view procurement');
    }

    public function view(User $user, Supplier $supplier): bool
    {
        return $this->sameOrg($user, $supplier) && $user->can('view procurement');
    }

    public function create(User $user): bool
    {
        return $user->can('manage suppliers');
    }

    public function update(User $user, Supplier $supplier): bool
    {
        return $this->sameOrg($user, $supplier) && $user->can('manage suppliers');
    }

    public function delete(User $user, Supplier $supplier): bool
    {
        return $this->sameOrg($user, $supplier) && $user->can('manage suppliers');
    }

    private function sameOrg(User $user, Supplier $supplier): bool
    {
        return $user->organization_id === $supplier->organization_id;
    }
}
