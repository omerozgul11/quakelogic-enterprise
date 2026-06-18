<?php

namespace App\Modules\Finance\Policies;

use App\Models\User;
use App\Modules\Finance\Models\CreditNote;

class CreditNotePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view finance');
    }

    public function view(User $user, CreditNote $note): bool
    {
        return $this->sameOrg($user, $note) && $user->can('view finance');
    }

    public function create(User $user): bool
    {
        return $user->can('manage finance');
    }

    public function update(User $user, CreditNote $note): bool
    {
        return $this->sameOrg($user, $note) && $user->can('manage finance');
    }

    public function delete(User $user, CreditNote $note): bool
    {
        return $this->sameOrg($user, $note) && $user->can('manage finance');
    }

    private function sameOrg(User $user, CreditNote $note): bool
    {
        return $user->organization_id === $note->organization_id;
    }
}
