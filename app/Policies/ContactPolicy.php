<?php

namespace App\Policies;

use App\Models\Contact;
use App\Models\User;

class ContactPolicy
{
    public function viewAny(User $user): bool { return $user->can('view crm'); }
    public function view(User $user, Contact $contact): bool { return $user->organization_id === $contact->organization_id && $user->can('view crm'); }
    public function create(User $user): bool { return $user->can('manage contacts'); }
    public function update(User $user, Contact $contact): bool { return $user->organization_id === $contact->organization_id && $user->can('manage contacts'); }
    public function delete(User $user, Contact $contact): bool { return $user->organization_id === $contact->organization_id && $user->can('manage contacts'); }
}
