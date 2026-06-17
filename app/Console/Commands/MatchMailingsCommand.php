<?php

namespace App\Console\Commands;

use App\Models\ProposalMailing;
use App\Services\Mailings\ShipmentProposalMatcher;
use Illuminate\Console\Command;

class MatchMailingsCommand extends Command
{
    protected $signature = 'mailings:match {--org= : Limit to a single organization id}';

    protected $description = 'Auto-link unlinked shipments to their matching proposals.';

    public function handle(ShipmentProposalMatcher $matcher): int
    {
        $orgIds = $this->option('org')
            ? [(int) $this->option('org')]
            : ProposalMailing::query()->whereNull('proposal_submission_id')
                ->distinct()->pluck('organization_id')->filter()->all();

        $total = 0;
        foreach ($orgIds as $orgId) {
            $linked = $matcher->matchOrganization((int) $orgId);
            $total += $linked;
            $this->info("Org {$orgId}: linked {$linked} shipment(s).");
        }

        $this->info("Done. Linked {$total} shipment(s) to proposals.");

        return self::SUCCESS;
    }
}
