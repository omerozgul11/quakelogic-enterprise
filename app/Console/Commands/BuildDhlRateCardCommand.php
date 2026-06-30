<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Turns a DHL Express contract rate card (the per-customer PDF DHL emails after a
 * Letter of Agreement) into the JSON the {@see \App\Services\Rating\DhlRateCard}
 * estimator reads. The card has no API and the layout is column-aligned, so this
 * consumes a `pdftotext -layout` dump rather than the raw PDF (the container ships
 * the pure-PHP smalot parser, which doesn't preserve the grid):
 *
 *   pdftotext -layout docs/dhl-express-rate-sheet-2026.pdf card.txt
 *   php artisan rating:build-dhl-card card.txt
 *
 * It parses the Export (US-outbound, lb) lane only — the primary lane for a US
 * shipper: destination zone map (A–N), the three weight bands (envelope /
 * document / standard 1–150 lb), the >150 lb per-lb multipliers, and the premium
 * add-ons. The revenue-based discount tiers are short and transcribed inline (the
 * two dynamic tables are not reliably distinguishable from the flat layout).
 */
class BuildDhlRateCardCommand extends Command
{
    protected $signature = 'rating:build-dhl-card
        {source : Path to a "pdftotext -layout" text dump of the DHL Express contract rate card}
        {--out=resources/rate-cards/dhl-express-worldwide-2026.json : Where to write the parsed JSON}';

    protected $description = 'Parse a DHL Express contract rate card (pdftotext -layout dump) into the JSON the estimator reads.';

    /** Zone columns, left-to-right, exactly as they appear in the export rate matrices. */
    private const ZONE_COLUMNS = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N'];

    public function handle(): int
    {
        $source = $this->resolve($this->argument('source'));
        if (! is_file($source)) {
            $this->error("Source text not found: {$source}");

            return self::FAILURE;
        }

        $lines = preg_split('/\R/', (string) file_get_contents($source)) ?: [];

        $meta = $this->parseMeta($lines);
        $countryZones = $this->parseExportZones($lines);
        [$bands, $multipliers] = $this->parseExportRates($lines);
        $premiums = $this->parsePremiums($lines);

        if ($countryZones === [] || ($bands['standard']['rates'] ?? []) === []) {
            $this->error('Parsing produced no zones or no standard rate band — is this a "pdftotext -layout" dump of the DHL card?');

            return self::FAILURE;
        }

        $card = [
            'carrier' => 'dhl',
            'product' => 'DHL EXPRESS WORLDWIDE',
            'lane' => 'export',
            'origin_country' => 'US',
            'currency' => 'USD',
            'weight_unit' => 'lb',
            'as_of' => $meta['as_of'],
            'customer' => $meta['customer'],
            'activation_id' => $meta['activation_id'],
            'source' => basename($source),
            'zones' => self::ZONE_COLUMNS,
            'country_zones' => $countryZones,
            'bands' => $bands,
            'multipliers' => $multipliers,
            'premiums' => $premiums,
            'discounts' => $this->discounts(),
        ];

        $out = $this->resolve($this->option('out'));
        if (! is_dir(dirname($out))) {
            mkdir(dirname($out), 0775, true);
        }
        file_put_contents($out, json_encode($card, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");

        $this->info('DHL rate card written: '.$out);
        $this->table(['Section', 'Count'], [
            ['Countries → zone', count($countryZones)],
            ['Envelope rows', count($bands['envelope']['rates'] ?? [])],
            ['Document rows', count($bands['document']['rates'] ?? [])],
            ['Standard rows', count($bands['standard']['rates'] ?? [])],
            ['>150 lb multiplier bands', count($multipliers)],
            ['Export discount tiers', count($card['discounts']['dynamic']['export'])],
        ]);

        return self::SUCCESS;
    }

    private function resolve(string $path): string
    {
        return str_starts_with($path, '/') ? $path : base_path($path);
    }

    /** @param array<int,string> $lines */
    private function parseMeta(array $lines): array
    {
        $text = implode("\n", $lines);

        $customer = null;
        if (preg_match('/Customer:\s*(.+)/', $text, $m)) {
            $customer = trim($m[1]);
        }

        $asOf = null;
        if (preg_match('/Ratecard as of:\s*([0-9]{2}-[A-Za-z]{3}-[0-9]{4})/', $text, $m)) {
            try {
                $asOf = Carbon::parse($m[1])->toDateString();
            } catch (\Throwable) {
                $asOf = $m[1];
            }
        }

        $activation = null;
        if (preg_match('/Activation ID:\s*([A-Z0-9-]+)/', $text, $m)) {
            $activation = $m[1];
        }

        return ['customer' => $customer, 'as_of' => $asOf, 'activation_id' => $activation];
    }

    /**
     * Country (ISO-2) → destination zone letter, read only from the EXPORT
     * "DHL EXPRESS WORLDWIDE DESTINATION ZONE" table (import / third-country pages
     * assign different zones to the same country, so they must not bleed in).
     *
     * @param  array<int,string>  $lines
     * @return array<string,string>
     */
    private function parseExportZones(array $lines): array
    {
        $start = $end = null;
        foreach ($lines as $i => $line) {
            if ($start === null && str_contains($line, 'DHL EXPRESS WORLDWIDE DESTINATION ZONE')) {
                $start = $i;

                continue;
            }
            if ($start !== null && str_contains($line, 'DHL EXPRESS WORLDWIDE EXPORT')) {
                $end = $i;
                break;
            }
        }
        if ($start === null) {
            return [];
        }

        $window = implode("\n", array_slice($lines, $start + 1, ($end ?? count($lines)) - $start - 1));

        // Each "Country (CC)  Z" pair — the layout packs up to four per line.
        preg_match_all('/\(([A-Z]{2})\)\s+([A-N])(?=\s|$)/m', $window, $matches, PREG_SET_ORDER);

        $zones = [];
        foreach ($matches as $m) {
            $zones[$m[1]] = $m[2];
        }
        ksort($zones);

        return $zones;
    }

    /**
     * Export rate matrices (lb). Walks the file tracking which product section and
     * which weight band each row belongs to, collecting only EXPORT rows.
     *
     * @param  array<int,string>  $lines
     * @return array{0: array<string,mixed>, 1: array<int,mixed>}
     */
    private function parseExportRates(array $lines): array
    {
        $bands = [
            'envelope' => ['max_lb' => 0.625, 'rates' => []],
            'document' => ['max_lb' => 4.0, 'rates' => []],
            'standard' => ['rates' => []],
        ];
        $multipliers = [];

        $product = null;   // export | import | third | null
        $band = null;      // envelope | document | standard | multiplier | null

        foreach ($lines as $line) {
            // Product section headers (check the more specific labels first).
            if (str_contains($line, 'THIRD COUNTRY')) {
                $product = 'third';
                $band = null;

                continue;
            }
            if (str_contains($line, 'IMPORT')) {
                $product = 'import';
                $band = null;

                continue;
            }
            if (str_contains($line, 'DHL EXPRESS WORLDWIDE EXPORT')) {
                $product = 'export';
                $band = null;

                continue;
            }

            if ($product === 'export') {
                if (str_contains($line, 'Envelope up to')) {
                    $band = 'envelope';

                    continue;
                }
                if (str_contains($line, 'Documents up to 4.0 LB')) {
                    $band = 'document';

                    continue;
                }
                if (str_contains($line, 'Non-documents from 1.0 LB')) {
                    $band = 'standard';

                    continue;
                }
                if (str_contains($line, 'Multiplier rate per 1 LB')) {
                    $band = 'multiplier';

                    continue;
                }
            }

            if ($product !== 'export' || $band === null) {
                continue;
            }

            $nums = $this->numericRow($line);
            if ($nums === null) {
                continue;
            }

            if ($band === 'multiplier') {
                // from, to, then one $/lb value per zone.
                if (count($nums) !== count(self::ZONE_COLUMNS) + 2) {
                    continue;
                }
                $multipliers[] = [
                    'from' => $nums[0],
                    'to' => $nums[1],
                    'rates' => array_combine(self::ZONE_COLUMNS, array_slice($nums, 2)),
                ];

                continue;
            }

            // envelope / document / standard: weight, then one rate per zone.
            if (count($nums) !== count(self::ZONE_COLUMNS) + 1) {
                continue;
            }
            $weight = number_format($nums[0], 1, '.', '');
            $bands[$band]['rates'][$weight] = array_combine(self::ZONE_COLUMNS, array_slice($nums, 1));
        }

        // Keep weights in ascending order for readable JSON + predictable lookups.
        foreach (['envelope', 'document', 'standard'] as $b) {
            uksort($bands[$b]['rates'], fn ($a, $c) => (float) $a <=> (float) $c);
        }

        return [$bands, $multipliers];
    }

    /**
     * A pure data row: every whitespace-separated token is numeric (commas allowed).
     * Returns the parsed floats, or null when the line is a header / footer / note.
     *
     * @return array<int,float>|null
     */
    private function numericRow(string $line): ?array
    {
        $line = trim($line);
        if ($line === '') {
            return null;
        }

        $tokens = preg_split('/\s+/', $line) ?: [];
        $nums = [];
        foreach ($tokens as $t) {
            if (! preg_match('/^[\d,]+(?:\.\d+)?$/', $t)) {
                return null;
            }
            $nums[] = (float) str_replace(',', '', $t);
        }

        return $nums === [] ? null : $nums;
    }

    /** @param array<int,string> $lines */
    private function parsePremiums(array $lines): array
    {
        $text = implode("\n", $lines);
        $premiums = [];

        if (preg_match('/Premium\s*9:00:\s*add\s*([\d.]+)\s*USD/i', $text, $m)) {
            $premiums['premium_9'] = (float) $m[1];
        }
        if (preg_match('/Premium\s*12:00:\s*add\s*([\d.]+)\s*USD/i', $text, $m)) {
            $premiums['premium_12'] = (float) $m[1];
        }

        return $premiums;
    }

    /**
     * Revenue-based discount tiers, transcribed from the card's Dynamic Discount
     * pages. Discounts apply to transport charges only (not surcharges/premiums).
     * Export = DHL Express Worldwide; the flat 55% is the Third-Country product.
     */
    private function discounts(): array
    {
        $tiers = fn (array $rows) => array_map(
            fn ($r) => ['min' => $r[0], 'max' => $r[1], 'pct' => $r[2]],
            $rows,
        );

        return [
            'basis' => 'monthly_transportation_revenue_usd',
            'note' => 'Discounts apply to transport charges only (exclude surcharges and premium add-ons).',
            'flat' => ['third_country' => 0.55],
            'dynamic' => [
                'export' => $tiers([
                    [0, 50, 0.40], [50, 100, 0.44], [100, 150, 0.46], [150, 200, 0.48],
                    [200, 250, 0.50], [250, 300, 0.55], [300, 450, 0.60], [450, 550, 0.64],
                    [550, 650, 0.68], [650, 750, 0.72], [750, 850, 0.74], [850, 950, 0.76],
                    [950, 1500, 0.78], [1500, 2500, 0.80], [2500, 6500, 0.82], [6500, 9500, 0.83],
                    [9500, 14000, 0.84], [14000, 20000, 0.85], [20000, 30000, 0.86],
                    [30000, 40000, 0.87], [40000, null, 0.88],
                ]),
                'import' => $tiers([
                    [0, 50, 0.28], [50, 100, 0.32], [100, 150, 0.34], [150, 200, 0.36],
                    [200, 250, 0.38], [250, 300, 0.43], [300, 450, 0.48], [450, 550, 0.52],
                    [550, 650, 0.56], [650, 750, 0.60], [750, 850, 0.62], [850, 950, 0.64],
                    [950, 1500, 0.66], [1500, 2500, 0.68], [2500, 6500, 0.70], [6500, 9500, 0.71],
                    [9500, 14000, 0.72], [14000, 20000, 0.73], [20000, 30000, 0.74],
                    [30000, 40000, 0.75], [40000, null, 0.76],
                ]),
            ],
        ];
    }
}
