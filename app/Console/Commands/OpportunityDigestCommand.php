<?php

namespace App\Console\Commands;

use App\Models\FollowUp;
use App\Models\Opportunity;
use App\Models\User;
use App\Notifications\ActivityNotification;
use App\Services\Mail\MailGatewayFactory;
use Illuminate\Console\Command;

/**
 * Each morning, send every user a digest of the opportunities matching their
 * personal keywords. The digest lands in their in-app Inbox (General thread)
 * and bell notifications always; it is also emailed when a work mailbox is
 * connected and FOLLOWUPS_DIGEST_SEND is enabled.
 *
 * This is the daily counterpart to the live, keyword-matched "For You" feed on
 * the Opportunities page.
 */
class OpportunityDigestCommand extends Command
{
    protected $signature = 'inbox:opportunity-digest {--dry-run : Preview without writing} {--org= : Limit to one organization} {--limit=10 : Max opportunities per digest}';

    protected $description = 'Send each user a morning digest of opportunities matching their keywords.';

    /** Columns scanned for a keyword match (mirrors the Opportunities filter). */
    private const COLUMNS = ['title', 'description', 'scope', 'requirements_summary', 'agency_name', 'solicitation_number', 'naics_code', 'matched_keywords'];

    public function handle(MailGatewayFactory $gateways): int
    {
        $dry = (bool) $this->option('dry-run');
        $send = (bool) config('followups.digest_send_enabled', false);
        $limit = max(1, (int) $this->option('limit'));

        $users = User::where('is_active', true)
            ->when($this->option('org'), fn ($q) => $q->where('organization_id', $this->option('org')))
            ->whereNotNull('pipeline_keywords')
            ->with('emailAccount')
            ->get();

        $sentInbox = 0;
        $emailed = 0;

        foreach ($users as $user) {
            $keywords = array_values(array_filter(array_map('trim', (array) $user->pipeline_keywords)));
            if (!$keywords) {
                continue;
            }
            if (($user->notification_preferences['channels']['digest'] ?? true) === false) {
                continue;
            }

            $matches = Opportunity::forOrganization($user->organization_id)
                ->active()
                ->where(function ($q) use ($keywords) {
                    foreach ($keywords as $kw) {
                        foreach (self::COLUMNS as $col) {
                            $q->orWhere($col, 'like', "%{$kw}%");
                        }
                    }
                })
                ->orderByDesc('posted_date')
                ->limit($limit)
                ->get(['id', 'title', 'agency_name', 'due_date', 'estimated_value', 'currency']);

            if ($matches->isEmpty()) {
                continue;
            }

            // Only digest opportunities the user hasn't already been digested on
            // today (idempotent if the command runs more than once).
            $alreadyToday = FollowUp::where('assigned_to', $user->id)
                ->whereNull('proposal_submission_id')
                ->where('type', 'digest')
                ->where('is_automated', true)
                ->whereDate('created_at', now()->toDateString())
                ->exists();
            if ($alreadyToday) {
                continue;
            }

            $subject = $matches->count() . ' ' . ($matches->count() === 1 ? 'opportunity' : 'opportunities') . ' matching your keywords';
            $body = $this->body($user, $matches, $keywords);

            if ($dry) {
                $this->line("  {$user->email}: {$matches->count()} match(es)");
                continue;
            }

            FollowUp::create([
                'organization_id' => $user->organization_id,
                'created_by' => $user->id,
                'assigned_to' => $user->id,
                'type' => 'digest',
                'status' => 'scheduled',
                'subject' => $subject,
                'message' => $body,
                'scheduled_date' => now()->toDateString(),
                'is_automated' => true,
            ]);

            $user->notify(new ActivityNotification([
                'type' => 'opportunity',
                'title' => $subject,
                'message' => 'Your morning opportunity digest is in your inbox.',
                'url' => route('opportunities.index', ['keywords' => $keywords]),
                'icon' => 'target',
            ]));
            $sentInbox++;

            // Email the digest when the user has connected their own work mailbox,
            // or when the global send flag is on (system mailer).
            $sendThis = $send || (bool) $user->emailAccount?->isConnected();
            if ($sendThis && $user->email) {
                $ok = $gateways->forUser($user)->send($user->email, $user->name, $subject, $body);
                if ($ok) {
                    $emailed++;
                }
            }
        }

        $note = $send ? '' : ' · emailed only to users with a connected work email (or set FOLLOWUPS_DIGEST_SEND=true for all)';
        $this->info(($dry ? '[dry-run] ' : '') . "Digests — users scanned: {$users->count()} · inbox: {$sentInbox} · emailed: {$emailed}{$note}");

        return self::SUCCESS;
    }

    /**
     * @param  \Illuminate\Support\Collection<int,Opportunity>  $matches
     * @param  array<int,string>  $keywords
     */
    private function body(User $user, $matches, array $keywords): string
    {
        $greeting = explode(' ', trim($user->name))[0] ?: 'there';
        $lines = ["Good morning {$greeting},", '', 'Here are the latest opportunities matching your keywords (' . implode(', ', $keywords) . '):', ''];

        foreach ($matches as $opp) {
            $bits = array_filter([
                $opp->agency_name,
                $opp->due_date ? 'due ' . $opp->due_date->format('M j, Y') : null,
                $opp->estimated_value ? ($opp->currency ?? 'USD') . ' ' . number_format((float) $opp->estimated_value) : null,
            ]);
            $lines[] = '• ' . $opp->title . ($bits ? ' — ' . implode(' · ', $bits) : '');
        }

        $lines[] = '';
        $lines[] = 'Open the Opportunities page to review and pursue them.';

        return implode("\n", $lines);
    }
}
