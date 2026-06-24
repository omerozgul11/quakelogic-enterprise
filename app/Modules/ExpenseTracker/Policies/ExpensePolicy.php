<?php

namespace App\Modules\ExpenseTracker\Policies;

use App\Models\User;
use App\Modules\ExpenseTracker\Models\Expense;

class ExpensePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view expenses');
    }

    public function view(User $user, Expense $expense): bool
    {
        return $this->sameOrg($user, $expense) && $user->can('view expenses');
    }

    /** Any user who can view the section may log their own expense. */
    public function create(User $user): bool
    {
        return $user->can('view expenses');
    }

    /** The owner may edit while still draft/rejected; managers may always edit. */
    public function update(User $user, Expense $expense): bool
    {
        if (! $this->sameOrg($user, $expense)) {
            return false;
        }
        if ($user->can('manage expenses')) {
            return true;
        }

        return $user->id === $expense->owner_id && $expense->status->canEdit();
    }

    public function delete(User $user, Expense $expense): bool
    {
        if (! $this->sameOrg($user, $expense)) {
            return false;
        }
        if ($user->can('manage expenses')) {
            return true;
        }

        return $user->id === $expense->owner_id && $expense->status->canEdit();
    }

    /** Submitting for approval — the owner (or a manager) pushes a draft forward. */
    public function submit(User $user, Expense $expense): bool
    {
        return $this->sameOrg($user, $expense)
            && ($user->id === $expense->owner_id || $user->can('manage expenses'));
    }

    /** Approve / reject / reimburse require the manage permission and no self-approval. */
    public function approve(User $user, Expense $expense): bool
    {
        return $this->sameOrg($user, $expense)
            && $user->can('manage expenses')
            && $user->id !== $expense->owner_id;
    }

    public function reject(User $user, Expense $expense): bool
    {
        return $this->approve($user, $expense);
    }

    public function reimburse(User $user, Expense $expense): bool
    {
        return $this->sameOrg($user, $expense) && $user->can('manage expenses');
    }

    private function sameOrg(User $user, Expense $expense): bool
    {
        return $user->organization_id === $expense->organization_id;
    }
}
