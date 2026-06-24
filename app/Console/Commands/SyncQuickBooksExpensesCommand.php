<?php

namespace App\Console\Commands;

use App\Modules\ExpenseTracker\Models\QuickBooksConnection;
use App\Modules\ExpenseTracker\Services\QuickBooksSyncService;
use Illuminate\Console\Command;

class SyncQuickBooksExpensesCommand extends Command
{
    protected $signature = 'quickbooks:sync {--org= : Limit to a single organization id}';

    protected $description = 'Pull expenses from connected QuickBooks Online companies (and push approved expenses where enabled).';

    public function handle(QuickBooksSyncService $sync): int
    {
        $connections = QuickBooksConnection::query()
            ->when($this->option('org'), fn ($q, $org) => $q->where('organization_id', $org))
            ->get();

        if ($connections->isEmpty()) {
            $this->info('No QuickBooks connections to sync.');

            return self::SUCCESS;
        }

        foreach ($connections as $connection) {
            $result = $sync->syncOrganization($connection);
            $this->line("• Org {$connection->organization_id}: imported {$result['imported']}, updated {$result['updated']}, pushed {$result['pushed']}.");
        }

        $this->info("Synced {$connections->count()} connection(s).");

        return self::SUCCESS;
    }
}
