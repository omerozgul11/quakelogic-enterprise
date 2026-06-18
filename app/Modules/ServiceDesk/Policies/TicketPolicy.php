<?php

namespace App\Modules\ServiceDesk\Policies;

use App\Models\User;
use App\Modules\ServiceDesk\Models\Ticket;

class TicketPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view tickets');
    }

    public function view(User $user, Ticket $ticket): bool
    {
        return $this->sameOrg($user, $ticket) && $user->can('view tickets');
    }

    public function create(User $user): bool
    {
        return $user->can('manage tickets');
    }

    public function update(User $user, Ticket $ticket): bool
    {
        return $this->sameOrg($user, $ticket) && $user->can('manage tickets');
    }

    public function delete(User $user, Ticket $ticket): bool
    {
        return $this->sameOrg($user, $ticket) && $user->can('manage tickets');
    }

    public function comment(User $user, Ticket $ticket): bool
    {
        return $this->sameOrg($user, $ticket) && $user->can('comment tickets');
    }

    private function sameOrg(User $user, Ticket $ticket): bool
    {
        return $user->organization_id === $ticket->organization_id;
    }
}
