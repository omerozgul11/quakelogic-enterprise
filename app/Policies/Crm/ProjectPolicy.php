<?php

namespace App\Policies\Crm;

use App\Models\Crm\Project;
use App\Models\Crm\Task;
use App\Models\User;

/**
 * Project authorization. "Admin" capability is the `manage all projects`
 * permission — a holder sees and manages every project in the org and reaches
 * settings. Without it a user only reaches projects they're assigned to (owner,
 * project manager, active team member or task assignee), and may edit one only
 * if they lead it (owner/PM). Team members can still update tasks assigned to
 * them via updateTask().
 */
class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view crm');
    }

    public function view(User $user, Project $project): bool
    {
        return $this->sameOrg($user, $project)
            && $user->can('view crm')
            && ($this->manageAll($user) || $project->isAssigned($user));
    }

    public function create(User $user): bool
    {
        return $user->can('manage projects');
    }

    /** Edit core project fields (title, description, dates, status, budget, notes). */
    public function update(User $user, Project $project): bool
    {
        return $this->sameOrg($user, $project)
            && ($this->manageAll($user) || $project->isLead($user));
    }

    public function delete(User $user, Project $project): bool
    {
        return $this->sameOrg($user, $project)
            && ($this->manageAll($user) || $project->owner_id === $user->id);
    }

    /** Add/remove/deactivate team members and assign roles. */
    public function manageTeam(User $user, Project $project): bool
    {
        return $this->sameOrg($user, $project)
            && ($this->manageAll($user) || $project->isLead($user));
    }

    /** Full task management — create, assign, delete, manage milestones/notes/files. */
    public function manageTasks(User $user, Project $project): bool
    {
        return $this->sameOrg($user, $project)
            && ($this->manageAll($user) || $project->isLead($user));
    }

    /** A team member may update a task assigned to them (status/progress only). */
    public function updateTask(User $user, Project $project, Task $task): bool
    {
        if (! $this->sameOrg($user, $project)) {
            return false;
        }

        return $this->manageAll($user)
            || $project->isLead($user)
            || $task->assigned_to === $user->id;
    }

    /** Change owner / project manager — an admin-only capability. */
    public function administer(User $user, Project $project): bool
    {
        return $this->sameOrg($user, $project) && $this->manageAll($user);
    }

    /** Reach and edit the org-level Project Management settings. */
    public function manageSettings(User $user): bool
    {
        return $user->can('manage all projects');
    }

    private function manageAll(User $user): bool
    {
        return $user->can('manage all projects');
    }

    private function sameOrg(User $user, Project $project): bool
    {
        return $user->organization_id === $project->organization_id;
    }
}
