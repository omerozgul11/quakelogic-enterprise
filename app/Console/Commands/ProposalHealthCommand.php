<?php

namespace App\Console\Commands;

use App\Enums\ProposalStatus;
use App\Models\FollowUp;
use App\Models\ProposalSubmission;
use App\Services\Notifications\Notifier;
use App\Services\Proposals\ProposalHealthService;
use Illuminate\Console\Command;

/**
 * Phase 2 — Proposal Health & Follow-Up Control (daily job).
 *
 * 1. No-contact escalation: for every active proposal, escalate when the days
 *    since the last logged client contact cross a tier (30 → owner, 45 → +manager,
 *    60 → +manager+admin). Each tier alerts once (tracked on the proposal) so the
 *    daily run never re-spams; logging a client contact resets the ladder.
 * 2. Pending-award control: a daily reminder for every proposal in "award_pending"
 *    until it moves to awarded / lost / cancelled.
 *
 * "Ensure no opportunity is forgotten."
 */
class ProposalHealthCommand extends Command
{
    protected $signature = 'proposals:health {--org= : Limit to one organization} {--dry-run : Report without notifying or writing}';

    protected $description = 'Escalate proposals with no recent client contact and nudge pending-award proposals daily.';

    public function handle(Notifier $notifier, ProposalHealthService $health): int
    {
        $dry = (bool) $this->option('dry-run');
        $today = now()->startOfDay();

        $active = ProposalSubmission::whereIn('status', $this->activeStatuses())
            ->when($this->option('org'), fn ($q) => $q->where('organization_id', $this->option('org')))
            ->with('teamMembers:id,proposal_submission_id,user_id')
            ->get();

        $escalated = 0;
        $pendingNudged = 0;

        foreach ($active as $proposal) {
            // --- 1. No-contact escalation ladder ---
            $tier = $health->escalationTier($proposal);
            if ($tier > (int) $proposal->health_escalation_level) {
                $days = $health->daysSinceContact($proposal);
                $recipients = $health->recipientsForTier($proposal, $tier);

                if (!$dry) {
                    $notifier->proposalHealthEscalation($proposal, $tier, $days, $recipients);
                    $proposal->forceFill(['health_escalation_level' => $tier])->saveQuietly();
                }
                $escalated++;
            }

            // --- 2. Pending-award daily reminder ---
            if ($proposal->status === ProposalStatus::Pending) {
                $daysWaiting = (int) ($proposal->submission_date
                    ? $proposal->submission_date->copy()->startOfDay()->diffInDays($today)
                    : 0);

                $alreadyToday = FollowUp::where('proposal_submission_id', $proposal->id)
                    ->where('type', 'award_reminder')
                    ->where('is_automated', true)
                    ->whereDate('created_at', $today->toDateString())
                    ->exists();

                if (!$dry) {
                    $notifier->proposalPendingAward($proposal, $daysWaiting);
                    if (!$alreadyToday) {
                        FollowUp::create([
                            'organization_id' => $proposal->organization_id,
                            'created_by' => $proposal->owner_id ?? $proposal->created_by,
                            'assigned_to' => $proposal->owner_id ?? $proposal->created_by,
                            'proposal_submission_id' => $proposal->id,
                            'type' => 'award_reminder',
                            'status' => 'scheduled',
                            'subject' => 'Awaiting award decision',
                            'message' => "\"{$proposal->project_name}\" ({$proposal->proposal_number}) is pending award"
                                . ($daysWaiting > 0 ? " — {$daysWaiting} day(s) since submission." : '.'),
                            'scheduled_date' => $today->toDateString(),
                            'is_automated' => true,
                        ]);
                    }
                }
                $pendingNudged++;
            }
        }

        $this->info(($dry ? '[dry-run] ' : '')
            . "Health check: {$active->count()} active proposal(s) · escalations: {$escalated} · pending-award nudges: {$pendingNudged}");

        return self::SUCCESS;
    }

    /** @return array<int,string> */
    private function activeStatuses(): array
    {
        return collect(ProposalStatus::cases())
            ->filter(fn (ProposalStatus $s) => $s->isActive())
            ->map(fn (ProposalStatus $s) => $s->value)
            ->values()->all();
    }
}
