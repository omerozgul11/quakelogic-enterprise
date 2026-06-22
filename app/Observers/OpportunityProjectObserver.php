<?php

namespace App\Observers;

use App\Enums\OpportunityStatus;
use App\Models\Opportunity;
use App\Services\Crm\ProjectCreationService;

/**
 * Spawns a managed CRM project when a user marks an opportunity as awarded.
 * Guarded so only interactive, user-initiated awards trigger it (console
 * imports / seeders run without an authenticated user and are skipped — they'd
 * otherwise create projects for bulk-imported "awarded" rows). The creation
 * itself is idempotent and de-duped against the opportunity's proposals, so an
 * opportunity award + a later proposal award never both spawn a project.
 */
class OpportunityProjectObserver
{
    public function updated(Opportunity $opportunity): void
    {
        if (! auth()->check()) {
            return;
        }
        if (! $opportunity->wasChanged('status') || $opportunity->status !== OpportunityStatus::Awarded) {
            return;
        }

        app(ProjectCreationService::class)->handleOpportunityAwarded($opportunity, auth()->user());
    }
}
