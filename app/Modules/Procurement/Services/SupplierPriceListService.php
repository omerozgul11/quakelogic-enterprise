<?php

namespace App\Modules\Procurement\Services;

use App\Models\User;
use App\Modules\Inventory\Enums\ProductType;
use App\Modules\Inventory\Imports\SheetImport;
use App\Modules\Inventory\Models\Product;
use App\Modules\Procurement\Models\Supplier;
use App\Modules\Procurement\Models\SupplierProduct;
use App\Services\Ai\AiProviderInterface;
use App\Services\Documents\DocumentTextExtractionService;
use App\Support\Currency;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

/**
 * Reads a supplier's price list / product sheet (xlsx, csv, PDF or image) into
 * line items {supplier SKU, name, price, currency} and matches each to an
 * existing inventory product. Spreadsheets are parsed deterministically; PDFs /
 * images go through the configured AI provider (text-first, then vision) with a
 * strict, never-guess schema. Nothing is written during parse() — the caller
 * shows a review screen and calls apply() with the confirmed lines.
 *
 * On apply the supplier's price becomes our purchasing COST (never the sale
 * price), a supplier↔product link is upserted, and unmatched lines can be
 * created as new products.
 */
class SupplierPriceListService
{
    public function __construct(
        private readonly AiProviderInterface $ai,
        private readonly DocumentTextExtractionService $text,
    ) {}

    /**
     * @return array{status:string,lines:array<int,array<string,mixed>>}
     *         status: ok | empty | unavailable
     */
    public function parse(UploadedFile $file, Supplier $supplier): array
    {
        $mime = $file->getMimeType() ?: '';
        $ext = strtolower($file->getClientOriginalExtension());
        $isSheet = in_array($ext, ['xlsx', 'xls', 'csv'], true)
            || str_contains($mime, 'spreadsheet') || str_contains($mime, 'excel') || $mime === 'text/csv';

        $raw = $isSheet ? $this->parseSheet($file) : $this->parseDocument($file, $mime);

        if ($raw === null) {
            return ['status' => 'unavailable', 'lines' => []];
        }
        if ($raw === []) {
            return ['status' => 'empty', 'lines' => []];
        }

        $lines = array_map(fn (array $l) => $this->matchLine($l, $supplier), $raw);

        return ['status' => 'ok', 'lines' => array_values($lines)];
    }

    /**
     * Apply the reviewed lines. Each line: action (update|create|skip),
     * product_id (for update), supplier_sku, name, price, currency.
     *
     * @param  array<int,array<string,mixed>>  $lines
     * @return array{updated:int,created:int,linked:int,skipped:int}
     */
    public function apply(Supplier $supplier, array $lines, User $user): array
    {
        $orgId = $supplier->organization_id;
        $updated = $created = $linked = $skipped = 0;

        DB::transaction(function () use ($lines, $supplier, $user, $orgId, &$updated, &$created, &$linked, &$skipped) {
            foreach ($lines as $line) {
                $action = $line['action'] ?? 'skip';
                if ($action === 'skip') {
                    $skipped++;
                    continue;
                }

                $price = isset($line['price']) && is_numeric($line['price']) ? round((float) $line['price'], 4) : null;
                $currency = $this->cleanCurrency($line['currency'] ?? null) ?? $this->cleanCurrency($supplier->currency) ?? 'USD';
                $name = trim((string) ($line['name'] ?? ''));
                $supplierSku = trim((string) ($line['supplier_sku'] ?? '')) ?: null;

                $product = null;

                if ($action === 'update') {
                    $product = Product::where('organization_id', $orgId)->find($line['product_id'] ?? 0);
                    if (! $product) {
                        $skipped++;
                        continue;
                    }
                    // Cost only — the supplier's price is our purchasing cost. The
                    // sale price (unit_price) is deliberately left untouched.
                    if ($price !== null) {
                        $product->forceFill(['unit_cost' => $price])->save();
                        $updated++;
                    }
                } elseif ($action === 'create') {
                    if ($name === '' && $supplierSku === null) {
                        $skipped++;
                        continue;
                    }
                    $product = Product::create([
                        'organization_id' => $orgId,
                        'created_by' => $user->id,
                        'owner_id' => $user->id,
                        'sku' => $this->uniqueSku($orgId, $supplierSku ?: $name),
                        'name' => $name !== '' ? $name : (string) $supplierSku,
                        'type' => ProductType::Good->value,
                        'unit_cost' => $price ?? 0,
                        'currency' => $currency,
                        'manufacturer' => $supplier->name,
                        'is_active' => true,
                    ]);
                    $created++;
                } else {
                    $skipped++;
                    continue;
                }

                $this->link($supplier, $product, $supplierSku, $price, $currency);
                $linked++;
            }
        });

        return ['updated' => $updated, 'created' => $created, 'linked' => $linked, 'skipped' => $skipped];
    }

    /** Upsert the supplier↔product link for this (product, supplier) pair. */
    private function link(Supplier $supplier, Product $product, ?string $supplierSku, ?float $price, string $currency): void
    {
        SupplierProduct::updateOrCreate(
            [
                'inventory_product_id' => $product->id,
                'procurement_supplier_id' => $supplier->id,
            ],
            [
                'organization_id' => $supplier->organization_id,
                'supplier_sku' => $supplierSku,
                'supplier_price' => $price,
                'currency' => $currency,
                'last_imported_at' => now(),
            ],
        );
    }

    /**
     * Best-effort match of one parsed line to an existing product, and the
     * chosen default action (update when matched, else create).
     *
     * @param  array<string,mixed>  $line
     * @return array<string,mixed>
     */
    private function matchLine(array $line, Supplier $supplier): array
    {
        $orgId = $supplier->organization_id;
        $sku = isset($line['supplier_sku']) ? trim((string) $line['supplier_sku']) : '';
        $name = isset($line['name']) ? trim((string) $line['name']) : '';
        $product = null;

        // 1. We already know this supplier's part number for a product.
        if ($sku !== '') {
            $existing = SupplierProduct::where('procurement_supplier_id', $supplier->id)
                ->where('supplier_sku', $sku)->first();
            if ($existing) {
                $product = Product::where('organization_id', $orgId)->find($existing->inventory_product_id);
            }
        }

        // 2. Their part number equals our SKU / MPN / barcode.
        if (! $product && $sku !== '') {
            $product = Product::where('organization_id', $orgId)
                ->where(fn ($q) => $q->where('sku', $sku)->orWhere('mpn', $sku)->orWhere('barcode', $sku))
                ->first();
        }

        // 3. Exact (case-insensitive) product name.
        if (! $product && $name !== '') {
            $product = Product::where('organization_id', $orgId)
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();
        }

        $match = $product ? [
            'product_id' => $product->id,
            'sku' => $product->sku,
            'name' => $product->name,
            'current_cost' => (float) $product->unit_cost,
            'currency' => $product->currency,
        ] : null;

        return [
            'supplier_sku' => $sku !== '' ? $sku : null,
            'name' => $name !== '' ? $name : null,
            'price' => isset($line['price']) && is_numeric($line['price']) ? round((float) $line['price'], 4) : null,
            'currency' => $this->cleanCurrency($line['currency'] ?? null),
            'match' => $match,
            'action' => $product ? 'update' : 'create',
        ];
    }

    /**
     * Deterministic spreadsheet parse. Locates the header row (needs a price
     * column plus a name or SKU column) and pulls line items.
     *
     * @return array<int,array<string,mixed>>
     */
    private function parseSheet(UploadedFile $file): array
    {
        try {
            $sheets = Excel::toArray(new SheetImport, $file);
        } catch (Throwable) {
            return [];
        }
        $rows = $sheets[0] ?? [];
        if ($rows === []) {
            return [];
        }

        $headerIdx = null;
        $cols = [];
        foreach ($rows as $i => $row) {
            $map = $this->detectColumns((array) $row);
            if (isset($map['price']) && (isset($map['name']) || isset($map['sku']))) {
                $headerIdx = $i;
                $cols = $map;
                break;
            }
            if ($i > 15) {
                break; // header should be near the top
            }
        }
        if ($headerIdx === null) {
            return [];
        }

        $lines = [];
        foreach (array_slice($rows, $headerIdx + 1) as $row) {
            $row = (array) $row;
            $sku = isset($cols['sku']) ? trim((string) ($row[$cols['sku']] ?? '')) : '';
            $name = isset($cols['name']) ? trim((string) ($row[$cols['name']] ?? '')) : '';
            $price = isset($cols['price']) ? $this->num((string) ($row[$cols['price']] ?? '')) : null;
            $currency = isset($cols['currency']) ? $this->cleanCurrency((string) ($row[$cols['currency']] ?? '')) : null;

            if ($sku === '' && $name === '') {
                continue;
            }
            $lines[] = [
                'supplier_sku' => $sku !== '' ? $sku : null,
                'name' => $name !== '' ? $name : null,
                'price' => $price,
                'currency' => $currency,
            ];
        }

        return $lines;
    }

    /**
     * Map spreadsheet header labels to our fields.
     *
     * @param  array<int,mixed>  $row
     * @return array<string,int>  field => column index
     */
    private function detectColumns(array $row): array
    {
        $cols = [];
        foreach ($row as $col => $header) {
            $n = $this->norm((string) $header);
            if ($n === '') {
                continue;
            }
            if (! isset($cols['sku']) && (str_contains($n, 'sku') || str_contains($n, 'partno') || str_contains($n, 'partnumber')
                || str_contains($n, 'itemno') || str_contains($n, 'itemcode') || str_contains($n, 'articleno')
                || str_contains($n, 'article') || str_contains($n, 'catalog') || str_contains($n, 'modelno')
                || str_contains($n, 'mpn') || $n === 'code' || $n === 'ref' || str_contains($n, 'ordercode'))) {
                $cols['sku'] = $col;

                continue;
            }
            if (! isset($cols['price']) && (str_contains($n, 'price') || str_contains($n, 'cost')
                || str_contains($n, 'netprice') || str_contains($n, 'unitprice') || str_contains($n, 'listprice')
                || str_contains($n, 'reseller') || str_contains($n, 'dealer') || str_contains($n, 'amount'))) {
                $cols['price'] = $col;

                continue;
            }
            if (! isset($cols['currency']) && (str_contains($n, 'currency') || $n === 'curr' || $n === 'ccy')) {
                $cols['currency'] = $col;

                continue;
            }
            if (! isset($cols['name']) && (str_contains($n, 'name') || str_contains($n, 'description')
                || str_contains($n, 'product') || str_contains($n, 'item') || str_contains($n, 'title'))) {
                $cols['name'] = $col;
            }
        }

        return $cols;
    }

    /**
     * AI parse of a PDF / image product sheet. Text-first, then native vision.
     * Returns null when no AI provider is available (so the caller can tell the
     * user to use a spreadsheet or type it in), [] when nothing was found.
     *
     * @return array<int,array<string,mixed>>|null
     */
    private function parseDocument(UploadedFile $file, string $mime): ?array
    {
        if (! $this->ai->isAvailable()) {
            return null;
        }

        $tmp = 'tmp/supplier-pricelist/'.Str::ulid().'.'.($file->getClientOriginalExtension() ?: 'bin');
        Storage::disk('local')->put($tmp, (string) file_get_contents($file->getRealPath()));

        try {
            $raw = $this->askProvider($tmp, $mime);
        } catch (Throwable) {
            $raw = '';
        } finally {
            Storage::disk('local')->delete($tmp);
        }

        $data = $this->decodeJsonObject($raw);
        if ($data === null) {
            return [];
        }

        $items = $data['items'] ?? (array_is_list($data) ? $data : []);
        if (! is_array($items)) {
            return [];
        }
        $listCurrency = $this->cleanCurrency($data['currency'] ?? null);

        $lines = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $sku = isset($item['sku']) && is_string($item['sku']) ? trim($item['sku']) : '';
            $name = isset($item['name']) && is_string($item['name']) ? trim($item['name']) : '';
            if ($sku === '' && $name === '') {
                continue;
            }
            $lines[] = [
                'supplier_sku' => $sku !== '' ? mb_substr($sku, 0, 255) : null,
                'name' => $name !== '' ? mb_substr($name, 0, 255) : null,
                'price' => isset($item['price']) && is_numeric($item['price']) ? round((float) $item['price'], 4) : null,
                'currency' => $this->cleanCurrency($item['currency'] ?? null) ?? $listCurrency,
            ];
        }

        return $lines;
    }

    private function askProvider(string $path, string $mime): string
    {
        $system = <<<'SYS'
            You read ONE supplier product price list / product sheet and return ONLY the
            products it explicitly lists, with each product's identifier, name, and unit
            price exactly as printed. Never invent a product, price, SKU, or currency — if a
            field is not printed, use null. Return ONE JSON object and nothing else: no
            prose, no markdown fences.
            SYS;

        $shape = <<<'TXT'
            Return this exact JSON shape:

            {
              "currency": "USD"|"EUR"|...|null,   // list-wide currency if the sheet states one for all prices
              "items": [
                {
                  "sku": string|null,             // the supplier's part / model / catalog / order number
                  "name": string|null,            // product name or description
                  "price": number|null,           // unit price as printed — plain number, no symbols or thousands separators
                  "currency": "USD"|"EUR"|...|null // per-line currency only if the line itself states one
                }
              ]
            }

            Include only rows that have at least a name or a SKU. If the document is not a
            product price list, return {"items": []}.
            TXT;

        $text = trim($this->text->extract($path, $mime));

        if (mb_strlen($text) >= 40) {
            return $this->ai->complete($system, $shape."\n\nPrice list text:\n".mb_substr($text, 0, 20000));
        }

        if ($this->ai->supportsVision()) {
            $bytes = (string) Storage::disk('local')->get($path);

            return $this->ai->generateFromMedia($system, $shape, [[
                'mime' => $mime,
                'data' => base64_encode($bytes),
            ]]);
        }

        return '';
    }

    /** @return array<string,mixed>|null */
    private function decodeJsonObject(string $raw): ?array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        // Strip markdown fences if the model added them despite instructions.
        $raw = preg_replace('/^```(?:json)?\s*|\s*```$/m', '', $raw) ?? $raw;
        $start = strpos($raw, '{');
        $end = strrpos($raw, '}');
        if ($start === false || $end === false || $end < $start) {
            return null;
        }
        $decoded = json_decode(substr($raw, $start, $end - $start + 1), true);

        return is_array($decoded) ? $decoded : null;
    }

    private function cleanCurrency(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $c = strtoupper(trim($value));

        return preg_match('/^[A-Z]{3}$/', $c) && in_array($c, Currency::codes(), true) ? $c : null;
    }

    private function uniqueSku(int $orgId, string $seed): string
    {
        $base = Str::upper(Str::slug(mb_substr($seed, 0, 40)));
        if ($base === '') {
            $base = 'SKU-'.Str::upper(Str::random(6));
        }
        $sku = $base;
        $i = 1;
        while (Product::where('organization_id', $orgId)->where('sku', $sku)->exists()) {
            $sku = $base.'-'.(++$i);
        }

        return $sku;
    }

    private function norm(string $label): string
    {
        return preg_replace('/[^a-z0-9]/', '', strtolower($label)) ?? '';
    }

    private function num(string $value): ?float
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        $value = preg_replace('/[^0-9.,-]/', '', $value) ?? '';
        if ($value === '' || $value === '-') {
            return null;
        }

        $lastComma = strrpos($value, ',');
        $lastDot = strrpos($value, '.');
        if ($lastComma !== false && $lastDot !== false) {
            if ($lastComma > $lastDot) {           // 1.234,56 → European
                $value = str_replace(['.', ','], ['', '.'], $value);
            } else {                                // 1,234.56 → US
                $value = str_replace(',', '', $value);
            }
        } elseif ($lastComma !== false) {
            $decimals = strlen($value) - $lastComma - 1;
            $value = $decimals === 2 ? str_replace(',', '.', $value) : str_replace(',', '', $value);
        }

        return is_numeric($value) ? (float) $value : null;
    }
}
