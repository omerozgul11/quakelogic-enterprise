<?php

namespace App\Modules\AssetManagement\Policies;

use App\Models\User;
use App\Modules\AssetManagement\Models\Asset;

class AssetPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view assets');
    }

    public function view(User $user, Asset $asset): bool
    {
        return $this->sameOrg($user, $asset) && $user->can('view assets');
    }

    public function create(User $user): bool
    {
        return $user->can('manage assets');
    }

    public function update(User $user, Asset $asset): bool
    {
        return $this->sameOrg($user, $asset) && $user->can('manage assets');
    }

    public function delete(User $user, Asset $asset): bool
    {
        return $this->sameOrg($user, $asset) && $user->can('manage assets');
    }

    public function maintain(User $user, Asset $asset): bool
    {
        return $this->sameOrg($user, $asset) && $user->can('manage maintenance');
    }

    private function sameOrg(User $user, Asset $asset): bool
    {
        return $user->organization_id === $asset->organization_id;
    }
}
