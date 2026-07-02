<?php

namespace App\Modules\Procurement\Policies;

use App\Models\User;
use App\Modules\Procurement\Models\Bill;

class BillPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view procurement');
    }

    public function view(User $user, Bill $bill): bool
    {
        return $this->sameOrg($user, $bill) && $user->can('view procurement');
    }

    public function create(User $user): bool
    {
        return $user->can('manage bills');
    }

    public function update(User $user, Bill $bill): bool
    {
        return $this->sameOrg($user, $bill) && $user->can('manage bills');
    }

    public function delete(User $user, Bill $bill): bool
    {
        return $this->sameOrg($user, $bill) && $user->can('manage bills');
    }

    public function approvePayment(User $user, Bill $bill): bool
    {
        return $this->sameOrg($user, $bill) && $user->can('approve bill payments');
    }

    private function sameOrg(User $user, Bill $bill): bool
    {
        return $user->organization_id === $bill->organization_id;
    }
}
