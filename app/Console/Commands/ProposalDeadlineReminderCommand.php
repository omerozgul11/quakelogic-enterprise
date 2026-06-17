<?php

namespace App\Console\Commands;

use App\Models\FollowUp;
use App\Models\ProposalSubmission;
use App\Services\Notifications\Notifier;
use Illuminate\Console\Command;

/**
 * Each day, remind everyone working a proposal that its deadline is within the
 * next few days. Fires an in-app notification and drops a reminder into the
 * proposal's inbox thread, so the team sees approaching deadlines without
 * having to go hunting for them.
 *
 * Final proposals (awarded/completed/lost/cancelled) are skipped — their due
 * date no longer matters.
 */
class ProposalDeadlineReminderCommand extends Command
{
    protected $signature = 'proposals:deadline-reminders {--days=5 : Remind when this many days or fewer remain} {--org= : Limit to one organization}';

    protected $description = 'Notify owners and team members of proposals whose deadline is approaching.';

    /** Statuses that are still in flight — a deadline still applies. */
    private const OPEN = ['in_progress', 'submitted', 'award_pending', 'clarification_requested'];

    public function handle(Notifier $notifier): int
    {
        $window = max(0, (int) $this->option('days'));
        $today = now()->startOfDay();
        $cutoff = now()->addDays($window)->endOfDay();

        $proposals = ProposalSubmission::whereIn('status', self::OPEN)
            ->whereNotNull('due_date')
            ->whereBetween('due_date', [$today->toDateString(), $cutoff->toDateString()])
            ->when($this->option('org'), fn ($q) => $q->where('organization_id', $this->option('org')))
            ->with('teamMembers:id,proposal_submission_id,user_id')
            ->get();

        $notified = 0;
        $threaded = 0;

        foreach ($proposals as $proposal) {
            $daysLeft = (int) $today->diffInDays($proposal->due_date->copy()->startOfDay(), false);

            $notifier->proposalDeadline($proposal, $daysLeft);
            $notified++;

            // One reminder in the proposal's inbox thread per day (idempotent).
            $alreadyToday = FollowUp::where('proposal_submission_id', $proposal->id)
                ->where('type', 'reminder')
                ->where('is_automated', true)
                ->whereDate('created_at', $today->toDateString())
                ->exists();
            if ($alreadyToday) {
                continue;
            }

            $when = $daysLeft <= 0 ? 'due today' : ($daysLeft === 1 ? 'due tomorrow' : "due in {$daysLeft} days");
            FollowUp::create([
                'organization_id' => $proposal->organization_id,
                'created_by' => $proposal->owner_id ?? $proposal->created_by,
                'assigned_to' => $proposal->owner_id ?? $proposal->created_by,
                'proposal_submission_id' => $proposal->id,
                'type' => 'reminder',
                'status' => 'scheduled',
                'subject' => 'Deadline reminder',
                'message' => "\"{$proposal->project_name}\" ({$proposal->proposal_number}) is {$when} — {$proposal->due_date->format('M j, Y')}.",
                'scheduled_date' => now()->toDateString(),
                'is_automated' => true,
            ]);
            $threaded++;
        }

        $this->info("Deadline reminders within {$window} day(s): {$proposals->count()} proposal(s) · notified: {$notified} · inbox entries: {$threaded}");

        return self::SUCCESS;
    }
}
