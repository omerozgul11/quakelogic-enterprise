<?php

namespace App\Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Enums\MovementType;
use App\Modules\Inventory\Enums\ProductType;
use App\Modules\Inventory\Http\Requests\ProductRequest;
use App\Modules\Inventory\Models\Movement;
use App\Modules\Inventory\Models\Product;
use App\Modules\Inventory\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            ->orderBy('name')
            ->paginate(20)->withQueryString()
            ->through(fn (Product $p) => [
                'id' => $p->id,
                'sku' => $p->sku,
                'name' => $p->name,
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
            'filters' => $request->only(['search', 'type', 'status']),
            'types' => ProductType::options(),
            'can' => [
                'manage' => $request->user()->can('manage products'),
                'adjust' => $request->user()->can('adjust stock'),
            ],
        ]);
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
                'total_on_hand' => $product->totalOnHand(),
                'stock_value' => round($product->stockValue(), 2),
            ],
            'stocks' => $stocks,
            'movements' => $movements,
            'warehouses' => Warehouse::where('organization_id', $orgId)->where('is_active', true)
                ->orderBy('name')->get(['id', 'name', 'code']),
            'movement_types' => collect(MovementType::cases())->map(fn ($t) => ['value' => $t->value, 'label' => $t->label()]),
            'types' => ProductType::options(),
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

        $product = Product::create([
            ...$request->validated(),
            'organization_id' => $user->organization_id,
            'created_by' => $user->id,
            'owner_id' => $user->id,
        ]);

        return redirect()
            ->route('inventory.products.show', $product)
            ->with('success', 'Product created.');
    }

    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        $this->authorize('update', $product);
        $product->update($request->validated());

        return back()->with('success', 'Product updated.');
    }

    public function destroy(Request $request, Product $product): RedirectResponse
    {
        $this->authorize('delete', $product);
        $product->delete();

        return redirect()->route('inventory.products.index')->with('success', "Product \"{$product->name}\" deleted.");
    }
}
