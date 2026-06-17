<?php

namespace App\Console\Commands;

use App\Models\ProposalSubmission;
use App\Services\Proposals\ProposalWorkflowService;
use Illuminate\Console\Command;

/**
 * Phase 5 — one-time (re-runnable) backfill: ensure every awarded/completed
 * proposal has a linked contract record. New awards get one automatically via
 * the workflow; this links proposals that were won before contracts existed.
 */
class BackfillContractsCommand extends Command
{
    protected $signature = 'contracts:backfill {--org= : Limit to one organization}';

    protected $description = 'Create linked contract records for won proposals that do not have one yet.';

    public function handle(ProposalWorkflowService $workflow): int
    {
        $proposals = ProposalSubmission::whereIn('status', ['awarded', 'completed'])
            ->whereDoesntHave('contract')
            ->when($this->option('org'), fn ($q) => $q->where('organization_id', $this->option('org')))
            ->get();

        foreach ($proposals as $proposal) {
            $workflow->ensureContract($proposal);
        }

        $this->info("Backfilled {$proposals->count()} contract(s) for won proposals.");

        return self::SUCCESS;
    }
}
