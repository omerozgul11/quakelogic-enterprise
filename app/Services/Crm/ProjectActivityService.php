<?php

namespace App\Services\Crm;

use App\Models\Crm\Project;
use App\Models\Crm\ProjectActivity;

/**
 * Writes human-readable entries to a project's activity feed (the per-project
 * audit trail). Pass a null userId for system/automation events.
 */
class ProjectActivityService
{
    /** @param array<string,mixed> $meta */
    public function log(Project $project, ?int $userId, string $action, string $description, array $meta = []): ProjectActivity
    {
        return $project->activities()->create([
            'organization_id' => $project->organization_id,
            'user_id' => $userId,
            'action' => $action,
            'description' => $description,
            'meta' => $meta !== [] ? $meta : null,
        ]);
    }
}
