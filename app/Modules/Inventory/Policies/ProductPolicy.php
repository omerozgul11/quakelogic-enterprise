<?php

namespace App\Modules\Inventory\Policies;

use App\Models\User;
use App\Modules\Inventory\Models\Product;

class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view inventory');
    }

    public function view(User $user, Product $product): bool
    {
        return $this->sameOrg($user, $product) && $user->can('view inventory');
    }

    public function create(User $user): bool
    {
        return $user->can('manage products');
    }

    public function update(User $user, Product $product): bool
    {
        return $this->sameOrg($user, $product) && $user->can('manage products');
    }

    public function delete(User $user, Product $product): bool
    {
        return $this->sameOrg($user, $product) && $user->can('manage products');
    }

    /** Stock receive / issue / adjust / count / transfer. */
    public function adjustStock(User $user, Product $product): bool
    {
        return $this->sameOrg($user, $product) && $user->can('adjust stock');
    }

    private function sameOrg(User $user, Product $product): bool
    {
        return $user->organization_id === $product->organization_id;
    }
}
