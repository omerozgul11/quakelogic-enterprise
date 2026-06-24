<?php

namespace App\Modules\ExpenseTracker\Policies;

use App\Models\User;
use App\Modules\ExpenseTracker\Models\RecurringExpense;

class RecurringExpensePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view expenses');
    }

    public function view(User $user, RecurringExpense $recurring): bool
    {
        return $this->sameOrg($user, $recurring) && $user->can('view expenses');
    }

    public function create(User $user): bool
    {
        return $user->can('manage expenses');
    }

    public function update(User $user, RecurringExpense $recurring): bool
    {
        return $this->sameOrg($user, $recurring) && $user->can('manage expenses');
    }

    public function delete(User $user, RecurringExpense $recurring): bool
    {
        return $this->sameOrg($user, $recurring) && $user->can('manage expenses');
    }

    private function sameOrg(User $user, RecurringExpense $recurring): bool
    {
        return $user->organization_id === $recurring->organization_id;
    }
}
