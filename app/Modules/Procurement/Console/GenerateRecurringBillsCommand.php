<?php

namespace App\Modules\Procurement\Console;

use App\Modules\Procurement\Services\BillService;
use Illuminate\Console\Command;

/**
 * Generates any recurring vendor bills that have come due. Scheduled daily; also
 * safe to run by hand.
 */
class GenerateRecurringBillsCommand extends Command
{
    protected $signature = 'procurement:generate-recurring-bills {--organization= : Only this organization id}';

    protected $description = 'Generate recurring procurement bills that are due';

    public function handle(BillService $bills): int
    {
        $org = $this->option('organization');
        $made = $bills->generateDueRecurring($org ? (int) $org : null);

        $this->info("Generated {$made} recurring bill".($made === 1 ? '' : 's').'.');

        return self::SUCCESS;
    }
}
