<?php

namespace App\Modules\Manufacturing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Models\Product;
use App\Modules\Manufacturing\Enums\BomStatus;
use App\Modules\Manufacturing\Http\Requests\BomRequest;
use App\Modules\Manufacturing\Models\Bom;
use App\Modules\Manufacturing\Models\BomItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class BomController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Bom::class);
        $orgId = $request->user()->organization_id;

        $boms = Bom::where('organization_id', $orgId)
            ->with('product:id,sku,name')
            ->withCount('items')
            ->when($request->search, fn ($q, $s) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$s}%")
                ->orWhereHas('product', fn ($p) => $p->where('sku', 'like', "%{$s}%")->orWhere('name', 'like', "%{$s}%"))))
            ->latest('id')
            ->paginate(20)->withQueryString()
            ->through(fn (Bom $b) => [
                'id' => $b->id,
                'name' => $b->name,
                'version' => $b->version,
                'product' => $b->product ? $b->product->sku.' · '.$b->product->name : '—',
                'status_label' => $b->status->label(),
                'status_color' => $b->status->color(),
                'items_count' => $b->items_count,
                'is_default' => $b->is_default,
            ]);

        return Inertia::render('Manufacturing/Boms/Index', [
            'boms' => $boms,
            'filters' => $request->only(['search']),
            'can' => ['manage' => $request->user()->can('manage boms')],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Bom::class);

        return Inertia::render('Manufacturing/Boms/Edit', [
            'bom' => null,
            'products' => $this->products($request),
            'statuses' => BomStatus::options(),
        ]);
    }

    public function store(BomRequest $request): RedirectResponse
    {
        $this->authorize('create', Bom::class);
        $user = $request->user();
        $data = $request->validated();

        $bom = DB::transaction(function () use ($data, $user) {
            $bom = Bom::create([
                'organization_id' => $user->organization_id,
                'created_by' => $user->id,
                'inventory_product_id' => $data['inventory_product_id'],
                'name' => $data['name'],
                'version' => $data['version'],
                'status' => $data['status'],
                'output_quantity' => $data['output_quantity'],
                'is_default' => $data['is_default'] ?? false,
                'notes' => $data['notes'] ?? null,
            ]);
            $this->syncItems($bom, $data['items']);

            return $bom;
        });

        return redirect()->route('manufacturing.boms.show', $bom)->with('success', 'BOM created.');
    }

    public function show(Request $request, Bom $bom): Response
    {
        $this->authorize('view', $bom);

        $bom->load(['product:id,sku,name,unit_of_measure', 'items' => fn ($q) => $q->orderBy('position'), 'items.product:id,sku,name,unit_cost,unit_of_measure']);

        $unitCost = (float) $bom->items->sum(fn (BomItem $i) => (float) $i->quantity_per * (float) ($i->product?->unit_cost ?? 0));

        return Inertia::render('Manufacturing/Boms/Show', [
            'bom' => [
                'id' => $bom->id,
                'name' => $bom->name,
                'version' => $bom->version,
                'status_label' => $bom->status->label(),
                'status_color' => $bom->status->color(),
                'output_quantity' => (float) $bom->output_quantity,
                'is_default' => $bom->is_default,
                'notes' => $bom->notes,
                'product' => ['id' => $bom->product?->id, 'sku' => $bom->product?->sku, 'name' => $bom->product?->name],
                'est_unit_cost' => round($bom->output_quantity > 0 ? $unitCost / (float) $bom->output_quantity : $unitCost, 4),
                'items' => $bom->items->map(fn (BomItem $i) => [
                    'id' => $i->id,
                    'product_id' => $i->inventory_product_id,
                    'sku' => $i->product?->sku,
                    'name' => $i->product?->name,
                    'unit_of_measure' => $i->product?->unit_of_measure,
                    'quantity_per' => (float) $i->quantity_per,
                    'unit_cost' => (float) ($i->product?->unit_cost ?? 0),
                    'notes' => $i->notes,
                ]),
            ],
            'can' => ['manage' => $request->user()->can('manage boms'), 'build' => $request->user()->can('manage work orders')],
        ]);
    }

    public function edit(Request $request, Bom $bom): Response
    {
        $this->authorize('update', $bom);

        $bom->load(['items' => fn ($q) => $q->orderBy('position')]);

        return Inertia::render('Manufacturing/Boms/Edit', [
            'bom' => [
                'id' => $bom->id,
                'inventory_product_id' => $bom->inventory_product_id,
                'name' => $bom->name,
                'version' => $bom->version,
                'status' => $bom->status->value,
                'output_quantity' => (float) $bom->output_quantity,
                'is_default' => $bom->is_default,
                'notes' => $bom->notes,
                'items' => $bom->items->map(fn (BomItem $i) => [
                    'inventory_product_id' => (string) $i->inventory_product_id,
                    'quantity_per' => (string) (float) $i->quantity_per,
                    'notes' => $i->notes ?? '',
                ]),
            ],
            'products' => $this->products($request),
            'statuses' => BomStatus::options(),
        ]);
    }

    public function update(BomRequest $request, Bom $bom): RedirectResponse
    {
        $this->authorize('update', $bom);
        $data = $request->validated();

        DB::transaction(function () use ($bom, $data) {
            $bom->update([
                'inventory_product_id' => $data['inventory_product_id'],
                'name' => $data['name'],
                'version' => $data['version'],
                'status' => $data['status'],
                'output_quantity' => $data['output_quantity'],
                'is_default' => $data['is_default'] ?? false,
                'notes' => $data['notes'] ?? null,
            ]);
            $bom->items()->delete();
            $this->syncItems($bom, $data['items']);
        });

        return redirect()->route('manufacturing.boms.show', $bom)->with('success', 'BOM updated.');
    }

    public function destroy(Request $request, Bom $bom): RedirectResponse
    {
        $this->authorize('delete', $bom);
        $bom->items()->delete();
        $bom->delete();

        return redirect()->route('manufacturing.boms.index')->with('success', 'BOM deleted.');
    }

    private function syncItems(Bom $bom, array $items): void
    {
        foreach (array_values($items) as $position => $line) {
            $bom->items()->create([
                'organization_id' => $bom->organization_id,
                'inventory_product_id' => $line['inventory_product_id'],
                'quantity_per' => $line['quantity_per'],
                'notes' => $line['notes'] ?? null,
                'position' => $position,
            ]);
        }
    }

    private function products(Request $request)
    {
        return Product::where('organization_id', $request->user()->organization_id)
            ->where('is_active', true)->orderBy('name')->get(['id', 'sku', 'name', 'unit_cost']);
    }
}
