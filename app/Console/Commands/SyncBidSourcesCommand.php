<?php

namespace App\Console\Commands;

use App\Models\Organization;
use App\Services\BidSources\BidPrime\BidPrimeImportService;
use App\Services\BidSources\SamGov\SamGovImportService;
use Illuminate\Console\Command;

class SyncBidSourcesCommand extends Command
{
    protected $signature = 'bids:sync {source=all : sam-gov, bidprime, or all}
                            {--organization= : Specific organization ID (syncs all if omitted)}
                            {--limit=100 : Max records to fetch per source}';

    protected $description = 'Sync bid opportunities from external sources';

    public function __construct(
        private readonly SamGovImportService $samGov,
        private readonly BidPrimeImportService $bidPrime
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $source = $this->argument('source');
        $orgId = $this->option('organization');

        $organizations = $orgId
            ? Organization::where('id', $orgId)->get()
            : Organization::all();

        if ($organizations->isEmpty()) {
            $this->error('No organizations found.');
            return Command::FAILURE;
        }

        foreach ($organizations as $org) {
            $this->info("Syncing for organization: {$org->name}");

            if (in_array($source, ['sam-gov', 'all']) && config('integrations.sam_gov.sync_enabled')) {
                $this->info('  → SAM.gov...');
                $import = $this->samGov->import($org, []);
                $this->line(sprintf(
                    '  ✓ SAM.gov: %d fetched, %d created, %d updated, %d skipped, %d errors',
                    $import->total_fetched,
                    $import->total_created,
                    $import->total_updated,
                    $import->total_skipped,
                    $import->total_errors
                ));
            }

            if (in_array($source, ['bidprime', 'all']) && config('integrations.bidprime.sync_enabled')) {
                $this->info('  → BidPrime...');
                $import = $this->bidPrime->import($org, []);
                $this->line(sprintf(
                    '  ✓ BidPrime: %d fetched, %d created, %d updated, %d skipped, %d errors',
                    $import->total_fetched,
                    $import->total_created,
                    $import->total_updated,
                    $import->total_skipped,
                    $import->total_errors
                ));
            }

            if ($source === 'all' && !config('integrations.sam_gov.sync_enabled') && !config('integrations.bidprime.sync_enabled')) {
                $this->warn('  All sync sources are disabled. Set SAM_GOV_SYNC_ENABLED or BIDPRIME_SYNC_ENABLED=true to enable.');
            }
        }

        $this->info('Sync complete.');
        return Command::SUCCESS;
    }
}
