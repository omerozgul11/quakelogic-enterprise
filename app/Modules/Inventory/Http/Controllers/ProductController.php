<?php

namespace App\Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Enums\MovementType;
use App\Modules\Inventory\Enums\ProductType;
use App\Modules\Inventory\Http\Requests\ProductRequest;
use App\Modules\Inventory\Models\Movement;
use App\Modules\Inventory\Models\Product;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Services\ProductCategorizer;
use App\Modules\Inventory\Services\ProductImportService;
use App\Support\Currency;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Product::class);
        $orgId = $request->user()->organization_id;

        $products = Product::where('organization_id', $orgId)
            ->withSum('stocks as on_hand', 'quantity_on_hand')
            ->when($request->search, fn ($q, $s) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$s}%")
                ->orWhere('sku', 'like', "%{$s}%")
                ->orWhere('barcode', 'like', "%{$s}%")
                ->orWhere('manufacturer', 'like', "%{$s}%")))
            ->when($request->type, fn ($q, $t) => $q->where('type', $t))
            ->when($request->status === 'active', fn ($q) => $q->where('is_active', true))
            ->when($request->status === 'inactive', fn ($q) => $q->where('is_active', false))
            ->when($request->status === 'low', fn ($q) => $q->whereNotNull('reorder_point')
                ->whereRaw('(select coalesce(sum(quantity_on_hand),0) from inventory_stocks where inventory_stocks.inventory_product_id = inventory_products.id) <= reorder_point'))
            ->tap(fn ($q) => $this->applySort($q, $request))
            ->paginate(20)->withQueryString()
            ->through(fn (Product $p) => [
                'id' => $p->id,
                'sku' => $p->sku,
                'name' => $p->name,
                'image_url' => $this->imageUrl($p),
                'type_label' => $p->type->label(),
                'type_color' => $p->type->color(),
                'category' => $p->category,
                'unit_of_measure' => $p->unit_of_measure,
                'unit_price' => (float) $p->unit_price,
                'unit_cost' => (float) $p->unit_cost,
                'currency' => $p->currency,
                'on_hand' => (float) $p->on_hand,
                'reorder_point' => $p->reorder_point !== null ? (float) $p->reorder_point : null,
                'is_low' => $p->reorder_point !== null && (float) $p->on_hand <= (float) $p->reorder_point,
                'is_active' => $p->is_active,
            ]);

        return Inertia::render('Inventory/Products/Index', [
            'products' => $products,
            'filters' => $request->only(['search', 'type', 'status', 'sort', 'dir']),
            'types' => ProductType::options(),
            'currencies' => Currency::options(),
            'can' => [
                'manage' => $request->user()->can('manage products'),
                'adjust' => $request->user()->can('adjust stock'),
            ],
        ]);
    }

    /**
     * Apply a whitelisted sort to the product list. Falls back to name A→Z.
     * Each sort uses name as a stable tiebreaker so equal values stay ordered.
     */
    private function applySort($query, Request $request): void
    {
        $dir = strtolower((string) $request->dir) === 'desc' ? 'desc' : 'asc';

        match ($request->sort) {
            'type' => $query->orderBy('type', $dir)->orderBy('name'),
            'price' => $query->orderBy('unit_price', $dir)->orderBy('name'),
            'name' => $query->orderBy('name', $dir),
            default => $query->orderBy('name', $dir === 'desc' ? 'desc' : 'asc'),
        };
    }

    public function show(Request $request, Product $product): Response
    {
        $this->authorize('view', $product);
        $orgId = $request->user()->organization_id;

        $product->load(['owner:id,name', 'creator:id,name']);

        $stocks = $product->stocks()->with('warehouse:id,name,code')->get()
            ->map(fn ($s) => [
                'warehouse_id' => $s->inventory_warehouse_id,
                'warehouse' => $s->warehouse?->name,
                'code' => $s->warehouse?->code,
                'on_hand' => (float) $s->quantity_on_hand,
                'reserved' => (float) $s->quantity_reserved,
                'available' => $s->available(),
                'average_cost' => (float) $s->average_cost,
                'value' => round((float) $s->quantity_on_hand * (float) $s->average_cost, 2),
            ])->values();

        $movements = $product->movements()
            ->with(['warehouse:id,name', 'creator:id,name'])
            ->latest('occurred_at')->latest('id')->limit(50)->get()
            ->map(fn (Movement $m) => [
                'id' => $m->id,
                'type' => $m->type->value,
                'type_label' => $m->type->label(),
                'type_color' => $m->type->color(),
                'quantity' => (float) $m->quantity,
                'quantity_after' => (float) $m->quantity_after,
                'unit_cost' => $m->unit_cost !== null ? (float) $m->unit_cost : null,
                'warehouse' => $m->warehouse?->name,
                'note' => $m->note,
                'by' => $m->creator?->name,
                'occurred_at' => $m->occurred_at?->toIso8601String(),
            ]);

        return Inertia::render('Inventory/Products/Show', [
            'product' => [
                'id' => $product->id,
                'ulid' => $product->ulid,
                'sku' => $product->sku,
                'name' => $product->name,
                'type' => $product->type->value,
                'type_label' => $product->type->label(),
                'type_color' => $product->type->color(),
                'category' => $product->category,
                'description' => $product->description,
                'image_url' => $this->imageUrl($product),
                'unit_of_measure' => $product->unit_of_measure,
                'barcode' => $product->barcode,
                'manufacturer' => $product->manufacturer,
                'mpn' => $product->mpn,
                'unit_cost' => (float) $product->unit_cost,
                'unit_price' => (float) $product->unit_price,
                'currency' => $product->currency,
                'reorder_point' => $product->reorder_point !== null ? (float) $product->reorder_point : null,
                'reorder_quantity' => $product->reorder_quantity !== null ? (float) $product->reorder_quantity : null,
                'lead_time_days' => $product->lead_time_days,
                'weight' => $product->weight !== null ? (float) $product->weight : null,
                'is_serialized' => $product->is_serialized,
                'track_inventory' => $product->track_inventory,
                'is_active' => $product->is_active,
                'metadata' => $product->metadata ?: null,
                'total_on_hand' => $product->totalOnHand(),
                'stock_value' => round($product->stockValue(), 2),
            ],
            'stocks' => $stocks,
            'movements' => $movements,
            'warehouses' => Warehouse::where('organization_id', $orgId)->where('is_active', true)
                ->orderBy('name')->get(['id', 'name', 'code']),
            'movement_types' => collect(MovementType::cases())->map(fn ($t) => ['value' => $t->value, 'label' => $t->label()]),
            'types' => ProductType::options(),
            'currencies' => Currency::options(),
            'can' => [
                'manage' => $request->user()->can('manage products'),
                'adjust' => $request->user()->can('adjust stock'),
            ],
        ]);
    }

    public function store(ProductRequest $request): RedirectResponse
    {
        $this->authorize('create', Product::class);
        $user = $request->user();
        $data = Arr::except($request->validated(), ['image', 'remove_image']);

        if ($request->hasFile('image')) {
            $data['image_path'] = $this->storeImage($request->file('image'), $user->organization_id);
        }

        $product = Product::create([
            ...$data,
            'organization_id' => $user->organization_id,
            'created_by' => $user->id,
            'owner_id' => $user->id,
        ]);

        return redirect()
            ->route('inventory.products.show', $product)
            ->with('success', 'Product created.');
    }

    /** Bulk-import products from an uploaded Excel/CSV spreadsheet. */
    public function import(Request $request, ProductImportService $importer): RedirectResponse
    {
        $this->authorize('create', Product::class);
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:10240'],
        ]);

        // TEMP DIAGNOSTIC: stash a copy of the real upload so the import failure
        // can be inspected against the actual bytes. Remove once resolved.
        try {
            $uploaded = $request->file('file');
            $request->file('file')->storeAs('import-debug', 'last-upload.'.strtolower($uploaded->getClientOriginalExtension() ?: 'dat'), 'local');
        } catch (\Throwable $e) {
            // never block the import on the diagnostic copy
        }

        $summary = $importer->import($request->file('file'), $request->user());

        $message = "Import complete — {$summary['created']} added, {$summary['updated']} updated"
            .($summary['skipped'] ? ", {$summary['skipped']} skipped" : '').'.';
        if ($summary['custom_fields'] !== []) {
            $message .= ' Extra columns kept on each product: '.implode(', ', $summary['custom_fields']).'.';
        }

        $redirect = redirect()->route('inventory.products.index')->with('success', $message);
        if ($summary['errors'] !== []) {
            $redirect->with('warning', count($summary['errors']).' row(s) had issues: '.implode(' ', array_slice($summary['errors'], 0, 3)));
        }

        return $redirect;
    }

    /**
     * Auto-assign categories to products from their names. By default only fills
     * products that have no category yet; mode=all re-derives every product's
     * category. Reports how many were set, per family.
     */
    public function autocategorize(Request $request, ProductCategorizer $categorizer): RedirectResponse
    {
        $this->authorize('create', Product::class);
        $orgId = $request->user()->organization_id;
        $all = $request->input('mode') === 'all';

        $updated = 0;
        $unmatched = 0;
        $breakdown = [];

        Product::where('organization_id', $orgId)
            ->when(! $all, fn ($q) => $q->where(fn ($w) => $w->whereNull('category')->orWhere('category', '')))
            ->select('id', 'name', 'category')
            ->chunkById(200, function ($products) use ($categorizer, &$updated, &$unmatched, &$breakdown) {
                foreach ($products as $product) {
                    $category = $categorizer->categorize($product->name);
                    if ($category === null) {
                        $unmatched++;
                        continue;
                    }
                    if ($category === $product->category) {
                        continue;
                    }
                    $product->update(['category' => $category]);
                    $updated++;
                    $breakdown[$category] = ($breakdown[$category] ?? 0) + 1;
                }
            });

        arsort($breakdown);
        $top = implode(', ', array_map(fn ($c, $n) => "{$c} ({$n})", array_keys($breakdown), array_values($breakdown)));

        $message = "Auto-categorized {$updated} product(s) by name"
            .($unmatched ? ", {$unmatched} left uncategorized (no clear match)" : '').'.';
        if ($top !== '') {
            $message .= ' '.$top.'.';
        }

        return redirect()->route('inventory.products.index')->with('success', $message);
    }

    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        $this->authorize('update', $product);
        $data = Arr::except($request->validated(), ['image', 'remove_image']);

        if ($request->boolean('remove_image') && $product->image_path) {
            Storage::disk('local')->delete($product->image_path);
            $data['image_path'] = null;
        }
        if ($request->hasFile('image')) {
            if ($product->image_path) {
                Storage::disk('local')->delete($product->image_path);
            }
            $data['image_path'] = $this->storeImage($request->file('image'), $product->organization_id);
        }

        $product->update($data);

        return back()->with('success', 'Product updated.');
    }

    public function destroy(Request $request, Product $product): RedirectResponse
    {
        $this->authorize('delete', $product);
        $product->delete();

        return redirect()->route('inventory.products.index')->with('success', "Product \"{$product->name}\" deleted.");
    }

    /** Stream the product photo (private disk, org/permission scoped). */
    public function image(Request $request, Product $product): mixed
    {
        $this->authorize('view', $product);
        abort_unless($product->image_path && Storage::disk('local')->exists($product->image_path), 404);

        return Storage::disk('local')->response($product->image_path, null, ['Cache-Control' => 'private, max-age=3600']);
    }

    private function storeImage(UploadedFile $file, int $organizationId): string
    {
        $stored = (string) Str::ulid().'.'.strtolower($file->getClientOriginalExtension() ?: 'jpg');

        return $file->storeAs("inventory-products/{$organizationId}", $stored, 'local');
    }

    /** Public-facing URL for the product photo, cache-busted when the image changes. */
    private function imageUrl(Product $product): ?string
    {
        return $product->image_path
            ? route('inventory.products.image', $product).'?v='.substr(md5($product->image_path), 0, 8)
            : null;
    }
}
