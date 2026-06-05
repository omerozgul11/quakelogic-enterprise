<?php

namespace App\Policies;

use App\Models\Agency;
use App\Models\User;

class AgencyPolicy
{
    public function viewAny(User $user): bool { return $user->can('view crm'); }
    public function view(User $user, Agency $agency): bool { return $user->organization_id === $agency->organization_id && $user->can('view crm'); }
    public function create(User $user): bool { return $user->can('manage agencies'); }
    public function update(User $user, Agency $agency): bool { return $user->organization_id === $agency->organization_id && $user->can('manage agencies'); }
    public function delete(User $user, Agency $agency): bool { return $user->organization_id === $agency->organization_id && $user->can('manage agencies'); }
}
