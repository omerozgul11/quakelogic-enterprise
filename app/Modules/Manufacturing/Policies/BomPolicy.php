<?php

namespace App\Modules\Manufacturing\Policies;

use App\Models\User;
use App\Modules\Manufacturing\Models\Bom;

class BomPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view manufacturing');
    }

    public function view(User $user, Bom $bom): bool
    {
        return $this->sameOrg($user, $bom) && $user->can('view manufacturing');
    }

    public function create(User $user): bool
    {
        return $user->can('manage boms');
    }

    public function update(User $user, Bom $bom): bool
    {
        return $this->sameOrg($user, $bom) && $user->can('manage boms');
    }

    public function delete(User $user, Bom $bom): bool
    {
        return $this->sameOrg($user, $bom) && $user->can('manage boms');
    }

    private function sameOrg(User $user, Bom $bom): bool
    {
        return $user->organization_id === $bom->organization_id;
    }
}
