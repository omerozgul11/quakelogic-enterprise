<?php

namespace App\Console\Commands;

use App\Models\FollowUp;
use App\Models\ProposalSubmission;
use App\Services\Mail\MailGatewayFactory;
use Illuminate\Console\Command;

/**
 * Once a month, create a status follow-up for every open proposal (and email it
 * to the client contact when sending is enabled). The follow-up records form the
 * conversation thread shown in the Gmail-style Follow-Ups view.
 *
 * Sending is OFF by default (FOLLOWUPS_MONTHLY_SEND) so nothing is emailed to
 * real recipients until a work mailbox is connected and the org opts in.
 */
class MonthlyProposalFollowUpCommand extends Command
{
    protected $signature = 'follow-ups:monthly {--dry-run : Preview without writing} {--org= : Limit to one organization}';

    protected $description = 'Create (and optionally send) a monthly status follow-up for each open proposal.';

    /** Proposal statuses that are awaiting a decision and warrant a monthly nudge. */
    private const OPEN = ['submitted', 'award_pending', 'clarification_requested'];

    public function handle(MailGatewayFactory $gateways): int
    {
        $dry = (bool) $this->option('dry-run');
        $send = (bool) config('followups.monthly_send_enabled', false);
        $created = 0;
        $sent = 0;
        $skipped = 0;

        $proposals = ProposalSubmission::whereIn('status', self::OPEN)
            ->when($this->option('org'), fn ($q) => $q->where('organization_id', $this->option('org')))
            ->with(['owner:id,name,email', 'company.contacts:id,company_id,first_name,last_name,email,is_key_contact'])
            ->get();

        foreach ($proposals as $proposal) {
            $alreadyThisMonth = FollowUp::where('proposal_submission_id', $proposal->id)
                ->where('type', 'status')
                ->where('is_automated', true)
                ->whereYear('created_at', now()->year)
                ->whereMonth('created_at', now()->month)
                ->exists();
            if ($alreadyThisMonth) {
                $skipped++;
                continue;
            }

            $contact = $proposal->company?->contacts
                ?->sortByDesc('is_key_contact')
                ->first(fn ($c) => !empty($c->email));

            $statusLabel = $proposal->status instanceof \BackedEnum ? $proposal->status->label() : ucfirst((string) $proposal->status);
            $subject = "Status update — {$proposal->project_name}";
            $body = $this->body($proposal, $statusLabel, $contact);

            if ($dry) {
                $this->line("  #{$proposal->id} {$proposal->proposal_number}" . ($contact ? " → {$contact->email}" : ' (no contact)'));
                $created++;
                continue;
            }

            $followUp = FollowUp::create([
                'organization_id' => $proposal->organization_id,
                'created_by' => $proposal->owner_id ?? $proposal->created_by,
                'assigned_to' => $proposal->owner_id ?? $proposal->created_by,
                'proposal_submission_id' => $proposal->id,
                'contact_id' => $contact?->id,
                'type' => 'status',
                'status' => 'scheduled',
                'subject' => $subject,
                'message' => $body,
                'scheduled_date' => now()->toDateString(),
                'is_automated' => true,
            ]);
            $created++;

            if ($send && $contact?->email) {
                $gateway = $gateways->forUser($proposal->owner);
                $ok = $gateway->send(
                    $contact->email,
                    trim($contact->first_name . ' ' . $contact->last_name),
                    $subject,
                    $body,
                    ['reply_to' => $proposal->owner?->email],
                );
                if ($ok) {
                    $followUp->update(['status' => 'sent', 'sent_at' => now()]);
                    $sent++;
                }
            }
        }

        $note = $send ? '' : ' · sending disabled (set FOLLOWUPS_MONTHLY_SEND=true once a mailbox is connected)';
        $this->info(($dry ? '[dry-run] ' : '') . "Open proposals: {$proposals->count()} · created: {$created} · emailed: {$sent} · skipped(existing): {$skipped}{$note}");

        return self::SUCCESS;
    }

    private function body(ProposalSubmission $proposal, string $status, $contact): string
    {
        $greeting = $contact && $contact->first_name ? $contact->first_name : 'there';
        $number = $proposal->proposal_number ? " ({$proposal->proposal_number})" : '';

        return "Hi {$greeting},\n\n"
            . "I wanted to check in on our proposal \"{$proposal->project_name}\"{$number}. "
            . "It is currently in the \"{$status}\" stage. Please let me know if you need any additional "
            . "information or have any questions — we're happy to help.\n\n"
            . "Thank you,\n"
            . ($proposal->owner?->name ?? 'The team');
    }
}
