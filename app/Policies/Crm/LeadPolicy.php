<?php

namespace App\Policies\Crm;

use App\Models\Crm\Lead;
use App\Models\User;

/**
 * Leads are intentionally open: anyone who can reach the CRM section may add,
 * edit or remove a lead (the section itself is gated by `access crm`).
 */
class LeadPolicy
{
    public function viewAny(User $user): bool { return $user->can('access crm'); }
    public function view(User $user, Lead $lead): bool { return $user->organization_id === $lead->organization_id && $user->can('access crm'); }
    public function create(User $user): bool { return $user->can('access crm'); }
    public function update(User $user, Lead $lead): bool { return $user->organization_id === $lead->organization_id && $user->can('access crm'); }
    public function delete(User $user, Lead $lead): bool { return $user->organization_id === $lead->organization_id && $user->can('access crm'); }
}
