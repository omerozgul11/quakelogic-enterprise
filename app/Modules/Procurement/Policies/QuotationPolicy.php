<?php

namespace App\Modules\Procurement\Policies;

use App\Models\User;
use App\Modules\Procurement\Models\Quotation;

class QuotationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view procurement');
    }

    public function view(User $user, Quotation $quotation): bool
    {
        return $this->sameOrg($user, $quotation) && $user->can('view procurement');
    }

    public function create(User $user): bool
    {
        return $user->can('manage quotations');
    }

    public function update(User $user, Quotation $quotation): bool
    {
        return $this->sameOrg($user, $quotation) && $user->can('manage quotations');
    }

    public function delete(User $user, Quotation $quotation): bool
    {
        return $this->sameOrg($user, $quotation) && $user->can('manage quotations');
    }

    private function sameOrg(User $user, Quotation $quotation): bool
    {
        return $user->organization_id === $quotation->organization_id;
    }
}
