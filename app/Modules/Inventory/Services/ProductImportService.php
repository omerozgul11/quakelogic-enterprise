<?php

namespace App\Modules\Inventory\Services;

use App\Models\User;
use App\Modules\Inventory\Enums\ProductType;
use App\Modules\Inventory\Imports\SheetImport;
use App\Modules\Inventory\Models\Product;
use App\Modules\Inventory\Models\Warehouse;
use App\Support\Currency;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Imports a product catalog from an uploaded Excel/CSV. Recognised columns map
 * to the standard product fields; everything else is kept verbatim on each
 * product's `metadata` (so no column on the sheet is ever lost). An optional
 * quantity column sets the on-hand. Upserts by SKU, so re-importing updates.
 */
class ProductImportService
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly ProductCategorizer $categorizer,
    ) {}

    /**
     * @return array{rows:int,created:int,updated:int,skipped:int,errors:array<int,string>,custom_fields:array<int,string>}
     */
    public function import(UploadedFile $file, User $user): array
    {
        $rows = $this->readRows($file);

        $headerIndex = $this->locateHeaderRow($rows);
        if ($headerIndex === null) {
            return ['rows' => 0, 'created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => ['The file appears to be empty.'], 'custom_fields' => []];
        }

        // Map each column to a product field (or null = keep as a custom field).
        $map = [];
        foreach ($rows[$headerIndex] as $col => $header) {
            $label = trim((string) $header);
            if ($label !== '') {
                $map[$col] = ['field' => $this->fieldFor($this->norm($label)), 'label' => $label];
            }
        }

        // If nothing maps to a name or SKU, treat the first column as the name so
        // rows still import (named by their first value) instead of all skipping.
        $hasKey = false;
        foreach ($map as $info) {
            if (in_array($info['field'], ['sku', 'name'], true)) {
                $hasKey = true;
                break;
            }
        }
        if (! $hasKey && ($firstCol = array_key_first($map)) !== null) {
            $map[$firstCol]['field'] = 'name';
        }

        $orgId = $user->organization_id;
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];
        $custom = [];

        foreach (array_slice($rows, $headerIndex + 1, null, true) as $i => $row) {
            $line = $i + 1;
            if (! $this->rowHasData($row)) {
                continue;
            }

            $attrs = [];
            $metadata = [];
            $quantity = null;

            foreach ($map as $col => $info) {
                $value = trim((string) ($row[$col] ?? ''));
                if ($value === '') {
                    continue;
                }

                if ($info['field'] === null) {
                    $metadata[$info['label']] = $value;
                    $custom[$info['label']] = true;
                    continue;
                }

                switch ($info['field']) {
                    case 'quantity':
                        $quantity = $this->num($value);
                        break;
                    case 'is_active':
                        $b = $this->bool($value);
                        if ($b !== null) {
                            $attrs['is_active'] = $b;
                        }
                        break;
                    case 'type':
                        $attrs['type'] = $this->resolveType($value);
                        break;
                    case 'currency':
                        $c = strtoupper($value);
                        $attrs['currency'] = in_array($c, Currency::codes(), true) ? $c : 'USD';
                        break;
                    case 'unit_cost':
                    case 'unit_price':
                    case 'weight':
                    case 'reorder_point':
                    case 'reorder_quantity':
                        $n = $this->num($value);
                        if ($n !== null) {
                            $attrs[$info['field']] = $n;
                        }
                        break;
                    case 'lead_time_days':
                        $n = $this->num($value);
                        if ($n !== null) {
                            $attrs['lead_time_days'] = (int) $n;
                        }
                        break;
                    default: // sku, name, description, category, unit_of_measure, barcode, manufacturer, mpn
                        $attrs[$info['field']] = $value;
                        break;
                }
            }

            $sku = $attrs['sku'] ?? null;
            $name = $attrs['name'] ?? null;
            if (! $sku && $name) {
                $sku = $this->skuFromName($name);
            }
            if (! $sku) {
                $skipped++;
                $errors[] = "Row {$line}: no SKU or product name — skipped.";
                continue;
            }

            try {
                $existing = Product::where('organization_id', $orgId)->where('sku', $sku)->first();

                // Derive a category from the name when the sheet didn't supply one
                // (never overwrites a category the product/sheet already has).
                if (empty($attrs['category']) && $name
                    && (! $existing || $existing->category === null || $existing->category === '')
                    && ($derived = $this->categorizer->categorize($name)) !== null) {
                    $attrs['category'] = $derived;
                }

                $product = $existing ?? new Product([
                    'organization_id' => $orgId,
                    'created_by' => $user->id,
                    'owner_id' => $user->id,
                    'sku' => $sku,
                    'name' => $name ?: $sku,
                    'type' => ProductType::Good->value,
                ]);

                $product->fill(Arr::except($attrs, ['sku']));
                if ($metadata !== []) {
                    $product->metadata = array_merge((array) $product->metadata, $metadata);
                }
                $product->save();

                if ($quantity !== null) {
                    try {
                        $this->inventory->count($product, $this->defaultWarehouse($orgId, $user), max(0.0, $quantity), ['note' => 'Imported from spreadsheet']);
                    } catch (\Throwable $e) {
                        $errors[] = "Row {$line}: saved, but stock not set ({$e->getMessage()}).";
                    }
                }

                $existing ? $updated++ : $created++;
            } catch (\Throwable $e) {
                $skipped++;
                $errors[] = "Row {$line}: {$e->getMessage()}";
            }
        }

        return [
            'rows' => count($rows) - $headerIndex - 1,
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'errors' => array_values(array_slice($errors, 0, 20)),
            'custom_fields' => array_keys($custom),
        ];
    }

    /**
     * Read the upload into raw rows. Excel files go through PhpSpreadsheet; CSVs
     * are read directly with our own delimiter detection (PhpSpreadsheet's guess
     * is easily thrown off by a title row like "Purchase | PORTAL").
     *
     * @return array<int,array<int,mixed>>
     */
    private function readRows(UploadedFile $file): array
    {
        $ext = strtolower($file->getClientOriginalExtension() ?: '');

        if (in_array($ext, ['csv', 'txt', ''], true)) {
            return $this->readCsv($file);
        }

        return Excel::toArray(new SheetImport, $file)[0] ?? [];
    }

    /** @return array<int,array<int,string>> */
    private function readCsv(UploadedFile $file): array
    {
        $content = (string) file_get_contents($file->getRealPath());
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? $content; // strip BOM
        $lines = preg_split('/\r\n|\r|\n/', $content) ?: [];

        // Pick the delimiter that splits rows into the most *consistent* column
        // count (so "Commodity Name" can't make spaces look like the delimiter).
        $best = ',';
        $bestScore = -1;
        foreach ([',', ';', "\t", '|'] as $delim) {
            $counts = [];
            foreach (array_slice($lines, 0, 40) as $line) {
                if (trim($line) === '') {
                    continue;
                }
                $c = count(str_getcsv($line, $delim, '"', '\\'));
                if ($c > 1) {
                    $counts[$c] = ($counts[$c] ?? 0) + 1;
                }
            }
            if ($counts === []) {
                continue;
            }
            arsort($counts);
            $mode = (int) array_key_first($counts);
            $score = $mode * $counts[$mode];
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $delim;
            }
        }

        $rows = [];
        foreach ($lines as $line) {
            $rows[] = str_getcsv($line, $best, '"', '\\');
        }

        return $rows;
    }

    /** @param array<int,mixed> $row */
    private function rowHasData(array $row): bool
    {
        foreach ($row as $cell) {
            if (trim((string) $cell) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * Find the real header row. Spreadsheet exports often have a title/banner row
     * (one merged cell) above the columns, so we scan the first rows and pick the
     * one that best matches known product columns, then the widest row, then the
     * first non-empty row.
     *
     * @param array<int,array<int,mixed>> $rows
     */
    private function locateHeaderRow(array $rows): ?int
    {
        $best = null;
        $bestScore = 0;
        $widest = null;
        $widestCount = 0;
        $firstNonEmpty = null;

        $limit = min(count($rows), 30);
        for ($i = 0; $i < $limit; $i++) {
            $nonEmpty = 0;
            $recognised = 0;
            foreach ($rows[$i] as $cell) {
                $label = trim((string) $cell);
                if ($label === '') {
                    continue;
                }
                $nonEmpty++;
                if ($this->fieldFor($this->norm($label)) !== null) {
                    $recognised++;
                }
            }
            if ($nonEmpty === 0) {
                continue;
            }
            if ($firstNonEmpty === null) {
                $firstNonEmpty = $i;
            }
            if ($recognised > $bestScore) {
                $bestScore = $recognised;
                $best = $i;
            }
            if ($nonEmpty > $widestCount) {
                $widestCount = $nonEmpty;
                $widest = $i;
            }
        }

        if ($best !== null && $bestScore >= 1) {
            return $best;
        }
        if ($widest !== null && $widestCount >= 2) {
            return $widest;
        }

        return $firstNonEmpty;
    }

    private function norm(string $s): string
    {
        return strtolower(preg_replace('/[^a-z0-9]/i', '', $s) ?? '');
    }

    /** Map a normalised header to a product field, or null to keep it as a custom field. */
    private function fieldFor(string $h): ?string
    {
        return match ($h) {
            'sku', 'skucode', 'productcode', 'itemcode', 'itemnumber', 'itemno', 'stockcode', 'code', 'partno',
            'commoditycode', 'materialcode', 'materialnumber', 'itemid', 'productid' => 'sku',
            'name', 'productname', 'itemname', 'item', 'product', 'title',
            'commodityname', 'itemdescription', 'productdescription', 'materialname', 'materialdescription' => 'name',
            'description', 'desc', 'details', 'longdescription' => 'description',
            'type', 'producttype', 'itemtype' => 'type',
            'category', 'productcategory', 'group', 'productgroup', 'groupname', 'commoditygroup', 'productline' => 'category',
            'uom', 'unit', 'units', 'unitofmeasure', 'measure', 'unitname', 'unitofmeasurement', 'uomname' => 'unit_of_measure',
            'barcode', 'upc', 'ean', 'gtin', 'upccode' => 'barcode',
            'manufacturer', 'brand', 'mfr', 'make' => 'manufacturer',
            'mpn', 'manufacturerpartnumber', 'mfrpartnumber', 'partnumber', 'modelnumber', 'model' => 'mpn',
            'cost', 'unitcost', 'costprice', 'buyprice', 'purchaseprice', 'avgcost' => 'unit_cost',
            'price', 'unitprice', 'sellprice', 'sellingprice', 'salesprice', 'retailprice', 'msrp', 'listprice',
            'rate', 'unitrate', 'rateusd' => 'unit_price',
            'currency', 'curr', 'ccy' => 'currency',
            'reorderpoint', 'reorderlevel', 'minstock', 'minimum', 'minqty' => 'reorder_point',
            'reorderquantity', 'reorderqty', 'orderqty' => 'reorder_quantity',
            'leadtime', 'leadtimedays', 'leaddays' => 'lead_time_days',
            'weight', 'wt' => 'weight',
            'quantity', 'qty', 'onhand', 'quantityonhand', 'qtyonhand', 'instock', 'stockonhand' => 'quantity',
            'active', 'isactive', 'enabled' => 'is_active',
            default => null,
        };
    }

    private function resolveType(string $raw): string
    {
        $n = $this->norm($raw);
        foreach (ProductType::cases() as $t) {
            if ($this->norm($t->value) === $n || $this->norm($t->label()) === $n) {
                return $t->value;
            }
        }

        return ProductType::Good->value;
    }

    private function num(string $v): ?float
    {
        $clean = preg_replace('/[^0-9.\-]/', '', $v) ?? '';

        return in_array($clean, ['', '-', '.', '-.'], true) ? null : (float) $clean;
    }

    private function bool(string $v): ?bool
    {
        $n = strtolower(trim($v));
        if (in_array($n, ['1', 'true', 'yes', 'y', 'active', 'enabled'], true)) {
            return true;
        }
        if (in_array($n, ['0', 'false', 'no', 'n', 'inactive', 'disabled'], true)) {
            return false;
        }

        return null;
    }

    private function skuFromName(string $name): string
    {
        $base = trim(strtoupper(preg_replace('/[^A-Za-z0-9]+/', '-', $name) ?? ''), '-');

        return $base !== '' ? Str::limit($base, 60, '') : 'SKU-'.strtoupper(Str::random(8));
    }

    private function defaultWarehouse(int $orgId, User $user): Warehouse
    {
        return Warehouse::where('organization_id', $orgId)->orderByDesc('is_default')->orderBy('id')->first()
            ?? Warehouse::create([
                'organization_id' => $orgId,
                'created_by' => $user->id,
                'name' => 'Main Warehouse',
                'code' => 'MAIN',
                'type' => 'main',
                'is_default' => true,
                'is_active' => true,
            ]);
    }
}
