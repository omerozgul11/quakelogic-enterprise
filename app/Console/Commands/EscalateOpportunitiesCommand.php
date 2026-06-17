<?php

namespace App\Console\Commands;

use App\Enums\OpportunityAssignmentStage;
use App\Models\Opportunity;
use App\Services\Notifications\Notifier;
use App\Services\Opportunities\OpportunityEscalationService;
use App\Services\Opportunities\OpportunityTimelineService;
use Illuminate\Console\Command;

/**
 * Hourly: escalate assigned-but-un-actioned opportunities up the accountability
 * ladder. An opportunity that has been Assigned (has an owner) but not yet moved
 * to In Progress climbs 24h → owner reminder, 48h → + manager, 72h → + admin,
 * 96h → reassignment candidate. Each tier fires once (tracked on
 * assignment_escalation_level); claiming/reassigning resets the clock.
 */
class EscalateOpportunitiesCommand extends Command
{
    protected $signature = 'opportunities:escalate {--org= : Limit to one organization} {--dry-run : Preview without notifying}';

    protected $description = 'Escalate assigned opportunities that have not been actioned (24/48/72/96h ladder).';

    public function handle(OpportunityEscalationService $escalation, Notifier $notifier, OpportunityTimelineService $timeline): int
    {
        $dry = (bool) $this->option('dry-run');

        $opportunities = Opportunity::query()
            ->active()
            ->where('assignment_stage', OpportunityAssignmentStage::Assigned->value)
            ->whereNotNull('owner_id')
            ->whereNotNull('assigned_at')
            ->when($this->option('org'), fn ($q) => $q->where('organization_id', $this->option('org')))
            ->get();

        $escalated = 0;

        foreach ($opportunities as $opportunity) {
            $hours = (int) $opportunity->assigned_at->diffInHours(now());
            $tier = $escalation->tierFor($hours);

            if ($tier === 0 || $tier <= (int) $opportunity->assignment_escalation_level) {
                continue;
            }

            $recipients = $escalation->recipientsForTier($opportunity, $tier);
            $deadline = $opportunity->days_until_deadline;
            $risk = $deadline !== null && $deadline <= 7
                ? ($deadline < 0 ? ' · deadline passed' : " · deadline in {$deadline}d")
                : '';
            $note = $tier >= 96
                ? ($opportunity->owner?->name ?? 'The owner') . " hasn't actioned this in {$hours}h — candidate for reassignment{$risk}."
                : ($opportunity->owner?->name ?? 'The owner') . " hasn't actioned this assignment in {$hours}h{$risk}.";

            if ($dry) {
                $this->line("  #{$opportunity->id} → tier {$tier}h ({$recipients->count()} recipient(s))");

                continue;
            }

            $notifier->opportunityEscalation($opportunity, $tier, $recipients, $note);
            $timeline->record($opportunity, OpportunityTimelineService::ESCALATED, "Escalated at {$tier}h of inaction.", null, ['tier' => $tier, 'hours' => $hours], touchActivity: false);
            $opportunity->forceFill(['assignment_escalation_level' => $tier])->saveQuietly();
            $escalated++;
        }

        $this->info(($dry ? '[dry-run] ' : '') . "Escalation checked {$opportunities->count()} assigned opportunit(ies); {$escalated} escalated.");

        return self::SUCCESS;
    }
}
