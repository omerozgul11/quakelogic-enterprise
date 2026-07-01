<?php

namespace App\Console\Commands;

use App\Models\Organization;
use App\Models\User;
use App\Modules\Inventory\Services\ProductImportService;
use App\Services\Reporting\ExchangeRateService;
use App\Support\Currency;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ImportPriceListCommand extends Command
{
    protected $signature = 'inventory:import-price-list
        {path : Path to the .xlsx or .csv price list on the server}
        {--margin=50 : Profit percent added to cost for the sale price}
        {--from=EUR : Source currency of the reseller prices}
        {--rate= : Override FX rate (USD per 1 unit of the source currency)}
        {--reseller-col= : Exact header of the reseller-price column (auto-detected otherwise)}
        {--org= : Organization id (default: first)}
        {--user= : Importing user id (default: first active in the org)}
        {--dry-run : Parse and report without writing to the database}';

    protected $description = 'Import a vendor price list into Inventory products (reseller → cost, + margin% sale, currency → USD).';

    public function handle(ProductImportService $service, ExchangeRateService $fx): int
    {
        $path = (string) $this->argument('path');
        if (! is_file($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $org = $this->option('org')
            ? Organization::find((int) $this->option('org'))
            : Organization::orderBy('id')->first();
        if (! $org) {
            $this->error('No organization found.');

            return self::FAILURE;
        }

        $user = $this->option('user')
            ? User::where('organization_id', $org->id)->find((int) $this->option('user'))
            : (User::where('organization_id', $org->id)->where('is_active', true)->orderBy('id')->first()
                ?? User::where('organization_id', $org->id)->orderBy('id')->first());
        if (! $user) {
            $this->error("No user found in organization #{$org->id}.");

            return self::FAILURE;
        }

        $from = strtoupper((string) $this->option('from'));
        $rate = $this->option('rate') !== null ? (float) $this->option('rate') : $this->fxRate($fx, $from);
        $margin = (float) $this->option('margin');

        $this->line(sprintf('Importing %s · %s→USD @ %.4f · margin %s%% · org #%d (as %s)',
            basename($path), $from, $rate, $margin, $org->id, $user->name));

        try {
            $rows = $service->readPath($path);
        } catch (\Throwable $e) {
            $this->error('Could not read the file: '.$e->getMessage());

            return self::FAILURE;
        }

        $r = $service->importPriceList($rows, $user, [
            'margin' => $margin,
            'from' => $from,
            'rate' => $rate,
            'reseller_col' => $this->option('reseller-col'),
            'dry_run' => (bool) $this->option('dry-run'),
        ]);

        foreach (($r['sample'] ?? []) as $s) {
            $this->line(sprintf('  • %-16s %-40s cat=%-14s cost $%.2f → sale $%.2f',
                $s['sku'], Str::limit((string) $s['name'], 38), $s['category'] ?? '-', $s['cost_usd'], $s['sale_usd']));
        }
        foreach (array_slice($r['errors'] ?? [], 0, 8) as $err) {
            $this->warn('  '.$err);
        }

        $this->info(sprintf('%s%d rows → %d created, %d updated, %d skipped.',
            $this->option('dry-run') ? '[DRY RUN] ' : '', $r['rows'], $r['created'], $r['updated'], $r['skipped']));

        return self::SUCCESS;
    }

    private function fxRate(ExchangeRateService $fx, string $code): float
    {
        if ($code === 'USD') {
            return 1.0;
        }
        try {
            foreach ($fx->dailyRates()['rates'] ?? [] as $row) {
                if (($row['code'] ?? null) === $code && (float) ($row['usd_per_unit'] ?? 0) > 0) {
                    return (float) $row['usd_per_unit'];
                }
            }
        } catch (\Throwable) {
        }
        try {
            return (float) Currency::rate($code);
        } catch (\Throwable) {
            return 1.0;
        }
    }
}
