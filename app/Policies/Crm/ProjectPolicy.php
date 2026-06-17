<?php

namespace App\Policies\Crm;

use App\Models\Crm\Project;
use App\Models\User;

class ProjectPolicy
{
    public function viewAny(User $user): bool { return $user->can('view crm'); }
    public function view(User $user, Project $project): bool { return $user->organization_id === $project->organization_id && $user->can('view crm'); }
    public function create(User $user): bool { return $user->can('manage projects'); }
    public function update(User $user, Project $project): bool { return $user->organization_id === $project->organization_id && $user->can('manage projects'); }
    public function delete(User $user, Project $project): bool { return $user->organization_id === $project->organization_id && $user->can('manage projects'); }
}
