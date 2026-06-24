<?php

namespace App\Modules\ExpenseTracker\Policies;

use App\Models\User;
use App\Modules\ExpenseTracker\Models\ExpenseCategory;

class ExpenseCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view expenses');
    }

    public function view(User $user, ExpenseCategory $category): bool
    {
        return $this->sameOrg($user, $category) && $user->can('view expenses');
    }

    public function create(User $user): bool
    {
        return $user->can('manage expenses');
    }

    public function update(User $user, ExpenseCategory $category): bool
    {
        return $this->sameOrg($user, $category) && $user->can('manage expenses');
    }

    public function delete(User $user, ExpenseCategory $category): bool
    {
        return $this->sameOrg($user, $category) && $user->can('manage expenses');
    }

    private function sameOrg(User $user, ExpenseCategory $category): bool
    {
        return $user->organization_id === $category->organization_id;
    }
}
