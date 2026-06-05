<?php

namespace App\Policies;

use App\Models\Commission;
use App\Models\User;

class CommissionPolicy
{
    public function viewAny(User $user): bool { return $user->can('view own commissions'); }
    public function view(User $user, Commission $commission): bool
    {
        if ($user->can('view all commissions')) return $user->organization_id === $commission->organization_id;
        return $user->can('view own commissions') && $commission->user_id === $user->id;
    }
    public function approve(User $user, Commission $commission): bool
    {
        return $user->organization_id === $commission->organization_id && $user->can('approve commissions');
    }
    public function manage(User $user): bool { return $user->can('manage commission rules'); }
}
