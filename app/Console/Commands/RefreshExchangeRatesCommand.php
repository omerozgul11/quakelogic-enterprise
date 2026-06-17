<?php

namespace App\Console\Commands;

use App\Services\Reporting\ExchangeRateService;
use Illuminate\Console\Command;

/**
 * Warm the daily exchange-rate cache each morning so the dashboard reads it
 * instantly (no per-request network wait). Safe to run repeatedly — it caches
 * the day's live rates, or no-ops to the reference fallback when the feed is
 * unavailable.
 */
class RefreshExchangeRatesCommand extends Command
{
    protected $signature = 'exchange-rates:refresh';

    protected $description = 'Fetch and cache today\'s USD exchange rates for the dashboard.';

    public function handle(ExchangeRateService $rates): int
    {
        $result = $rates->dailyRates();
        $this->info("Exchange rates ready ({$result['source']}, {$result['date']}): " . count($result['rates']) . ' currencies.');

        return self::SUCCESS;
    }
}
