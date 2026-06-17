<?php

namespace App\Policies\Crm;

use App\Models\Crm\Lead;
use App\Models\User;

class LeadPolicy
{
    public function viewAny(User $user): bool { return $user->can('view crm'); }
    public function view(User $user, Lead $lead): bool { return $user->organization_id === $lead->organization_id && $user->can('view crm'); }
    public function create(User $user): bool { return $user->can('manage leads'); }
    public function update(User $user, Lead $lead): bool { return $user->organization_id === $lead->organization_id && $user->can('manage leads'); }
    public function delete(User $user, Lead $lead): bool { return $user->organization_id === $lead->organization_id && $user->can('manage leads'); }
}
