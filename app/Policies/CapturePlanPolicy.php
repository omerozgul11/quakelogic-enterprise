<?php

namespace App\Policies;

use App\Models\CapturePlan;
use App\Models\User;

class CapturePlanPolicy
{
    public function viewAny(User $user): bool { return $user->can('view capture plans'); }
    public function view(User $user, CapturePlan $plan): bool
    {
        return $user->organization_id === $plan->organization_id && $user->can('view capture plans');
    }
    public function manage(User $user, CapturePlan $plan): bool
    {
        return $user->organization_id === $plan->organization_id && $user->can('manage capture plans');
    }
    public function create(User $user): bool { return $user->can('manage capture plans'); }
    public function update(User $user, CapturePlan $plan): bool { return $this->manage($user, $plan); }
    public function delete(User $user, CapturePlan $plan): bool { return $this->manage($user, $plan); }
}
