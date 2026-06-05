<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;

class CompanyPolicy
{
    public function viewAny(User $user): bool { return $user->can('view crm'); }
    public function view(User $user, Company $company): bool { return $user->organization_id === $company->organization_id && $user->can('view crm'); }
    public function create(User $user): bool { return $user->can('manage companies'); }
    public function update(User $user, Company $company): bool { return $user->organization_id === $company->organization_id && $user->can('manage companies'); }
    public function delete(User $user, Company $company): bool { return $user->organization_id === $company->organization_id && $user->can('manage companies'); }
}
