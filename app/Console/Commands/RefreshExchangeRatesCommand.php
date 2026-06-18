<?php

namespace App\Console\Commands;

use App\Services\Reporting\ExchangeRateService;
use Illuminate\Console\Command;

/**
 * Refresh the exchange-rate cache so the dashboard reads it instantly (no
 * per-request network wait). Re-fetches live rates on every run — bypassing the
 * cached copy — so when scheduled every few minutes the dashboard stays current;
 * a failed pull falls back to the reference rates and stays uncached so the next
 * run retries.
 */
class RefreshExchangeRatesCommand extends Command
{
    protected $signature = 'exchange-rates:refresh';

    protected $description = 'Fetch and cache the latest USD exchange rates for the dashboard.';

    public function handle(ExchangeRateService $rates): int
    {
        $result = $rates->refresh();
        $this->info("Exchange rates refreshed ({$result['source']}, {$result['date']}): " . count($result['rates']) . ' currencies.');

        return self::SUCCESS;
    }
}
