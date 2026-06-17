<?php

namespace App\Console\Commands;

use App\Models\FollowUp;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\ActivityNotification;
use App\Services\Mail\MailGatewayFactory;
use App\Services\Opportunities\OpportunityOversightService;
use Illuminate\Console\Command;

/**
 * Each morning, deliver an executive opportunity briefing to every admin/CEO:
 * what was found, what's assigned/unassigned, what's at risk, who's overloaded,
 * and which opportunities should be reassigned. Lands in their Inbox (General
 * thread) + bell, and emails when a work mailbox is connected. The CEO opens one
 * dashboard (the Command Center) and reads this to know where to look.
 */
class ExecutiveBriefingCommand extends Command
{
    protected $signature = 'executive:briefing {--dry-run : Preview without writing} {--org= : Limit to one organization}';

    protected $description = 'Send admins/CEO a daily executive opportunity briefing.';

    public function handle(OpportunityOversightService $oversight, MailGatewayFactory $gateways): int
    {
        $dry = (bool) $this->option('dry-run');

        $orgs = Organization::query()
            ->when($this->option('org'), fn ($q) => $q->where('id', $this->option('org')))
            ->where('is_active', true)
            ->get();

        $sent = 0;

        foreach ($orgs as $org) {
            $recipients = User::where('organization_id', $org->id)
                ->where('is_active', true)
                ->with('emailAccount')
                ->get()
                ->filter(fn (User $u) => $u->can('view executive dashboard'));

            if ($recipients->isEmpty()) {
                continue;
            }

            $summary = $oversight->summary($org->id);
            $subject = $this->subject($summary);
            $body = $this->body($summary);

            foreach ($recipients as $user) {
                if (($user->notification_preferences['channels']['digest'] ?? true) === false) {
                    continue;
                }

                $already = FollowUp::where('assigned_to', $user->id)
                    ->where('type', 'briefing')
                    ->where('is_automated', true)
                    ->whereDate('created_at', now()->toDateString())
                    ->exists();
                if ($already) {
                    continue;
                }

                if ($dry) {
                    $this->line("  {$user->email}: {$subject}");

                    continue;
                }

                FollowUp::create([
                    'organization_id' => $org->id,
                    'created_by' => $user->id,
                    'assigned_to' => $user->id,
                    'type' => 'briefing',
                    'status' => 'scheduled',
                    'subject' => $subject,
                    'message' => $body,
                    'scheduled_date' => now()->toDateString(),
                    'is_automated' => true,
                ]);

                $user->notify(new ActivityNotification([
                    'type' => 'opportunity',
                    'title' => 'Executive briefing ready',
                    'message' => $subject,
                    'url' => route('dashboard.opportunities'),
                    'icon' => 'gauge',
                ]));
                $sent++;

                if ($user->emailAccount?->isConnected() && $user->email) {
                    $gateways->forUser($user)->send($user->email, $user->name, $subject, $body);
                }
            }
        }

        $this->info(($dry ? '[dry-run] ' : '') . "Executive briefings sent: {$sent}.");

        return self::SUCCESS;
    }

    /** @param array<string,mixed> $s */
    private function subject(array $s): string
    {
        $c = $s['counts'];

        return "Executive briefing — {$c['discovered_today']} found today · {$c['unassigned']} unassigned · {$s['at_risk']['flagged_total']} at risk";
    }

    /** @param array<string,mixed> $s */
    private function body(array $s): string
    {
        $c = $s['counts'];
        $m = $s['metrics'];
        $pct = fn ($v) => $v === null ? '—' : $v . '%';

        $lines = [
            'Good morning,',
            '',
            'Here is your opportunity briefing for ' . now()->format('l, M j') . ':',
            '',
            "• Found today: {$c['discovered_today']}",
            "• Active: {$c['active']}  (assigned {$c['assigned']}, unassigned {$c['unassigned']})",
            "• Overdue: {$c['overdue']}  ·  inactive 7d+: {$c['inactive']}",
            "• At risk: {$s['at_risk']['flagged_total']}  ({$s['at_risk']['critical']} critical, {$s['at_risk']['warning']} warning)",
            "• Submitted: {$c['submitted']}  ·  Won: {$c['won']}  ·  Lost: {$c['lost']}",
            '',
            "Win rate {$pct($m['win_rate'])} · assignment rate {$pct($m['assignment_rate'])} · velocity {$m['proposal_velocity_per_week']}/wk",
            '',
        ];

        $high = collect($s['workload']['highest'])->take(2)->map(fn ($u) => "{$u['name']} ({$u['workload']})")->all();
        $low = collect($s['workload']['lowest'])->take(2)->map(fn ($u) => "{$u['name']} ({$u['workload']})")->all();
        if ($high) {
            $lines[] = 'Highest workload: ' . implode(', ', $high);
        }
        if ($low) {
            $lines[] = 'Most available: ' . implode(', ', $low);
        }

        if (! empty($s['reassignments'])) {
            $lines[] = '';
            $lines[] = 'Recommended reassignments:';
            foreach (array_slice($s['reassignments'], 0, 5) as $r) {
                $lines[] = "• {$r['title']} — {$r['current_owner']} → {$r['suggested_owner']}";
            }
        }

        $lines[] = '';
        $lines[] = 'Open the Command Center for the full picture.';

        return implode("\n", $lines);
    }
}
