<?php

namespace App\Console\Commands;

use App\Models\Crm\FollowUp;
use App\Services\Notifications\Notifier;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Daily nudge: in-app reminder to each assignee for open CRM follow-ups due today
 * or already overdue. `reminded_on` guards against re-notifying the same day, so
 * a missed/overdue follow-up keeps reminding once per day until it's done.
 */
class CrmFollowUpRemindersCommand extends Command
{
    protected $signature = 'crm:follow-up-reminders {--dry-run : List who would be reminded without sending}';

    protected $description = 'Remind assignees of CRM follow-ups due today or overdue';

    public function handle(Notifier $notifier): int
    {
        $today = Carbon::now()->toDateString();

        $due = FollowUp::query()
            ->where('status', 'open')
            ->whereDate('due_date', '<=', $today)
            ->whereNotNull('assigned_to')
            ->where(fn ($q) => $q->whereNull('reminded_on')->orWhereDate('reminded_on', '<', $today))
            ->with('assignee:id,name,is_active,notification_preferences')
            ->get();

        $sent = 0;
        foreach ($due as $followUp) {
            if ($this->option('dry-run')) {
                $this->line("Would remind {$followUp->assignee?->name}: {$followUp->title} (due {$followUp->due_date?->toDateString()})");
                continue;
            }

            $notifier->crmFollowUpDue($followUp);
            $followUp->forceFill(['reminded_on' => $today])->saveQuietly();
            $sent++;
        }

        $this->info($this->option('dry-run')
            ? "{$due->count()} follow-up(s) would be reminded."
            : "Reminded {$sent} follow-up(s).");

        return self::SUCCESS;
    }
}
