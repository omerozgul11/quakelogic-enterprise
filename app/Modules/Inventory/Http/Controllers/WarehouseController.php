<?php

namespace App\Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Http\Requests\WarehouseRequest;
use App\Modules\Inventory\Models\Location;
use App\Modules\Inventory\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class WarehouseController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Warehouse::class);
        $orgId = $request->user()->organization_id;

        $warehouses = Warehouse::where('organization_id', $orgId)
            ->withCount('locations')
            ->orderByDesc('is_default')->orderBy('name')
            ->get()
            ->map(fn (Warehouse $w) => [
                'id' => $w->id,
                'code' => $w->code,
                'name' => $w->name,
                'type' => $w->type,
                'city' => $w->city,
                'state' => $w->state,
                'is_default' => $w->is_default,
                'is_active' => $w->is_active,
                'locations_count' => $w->locations_count,
                'sku_count' => DB::table('inventory_stocks')->where('inventory_warehouse_id', $w->id)->where('quantity_on_hand', '!=', 0)->count(),
                'value' => round((float) DB::table('inventory_stocks')->where('inventory_warehouse_id', $w->id)->sum(DB::raw('quantity_on_hand * average_cost')), 2),
            ]);

        return Inertia::render('Inventory/Warehouses/Index', [
            'warehouses' => $warehouses,
            'can' => ['manage' => $request->user()->can('manage warehouses')],
        ]);
    }

    public function show(Request $request, Warehouse $warehouse): Response
    {
        $this->authorize('view', $warehouse);

        $warehouse->load(['locations' => fn ($q) => $q->orderBy('code')]);

        $stock = $warehouse->stocks()->with('product:id,sku,name,unit_of_measure')->get()
            ->filter(fn ($s) => (float) $s->quantity_on_hand != 0.0)
            ->map(fn ($s) => [
                'product_id' => $s->inventory_product_id,
                'sku' => $s->product?->sku,
                'name' => $s->product?->name,
                'unit_of_measure' => $s->product?->unit_of_measure,
                'on_hand' => (float) $s->quantity_on_hand,
                'average_cost' => (float) $s->average_cost,
                'value' => round((float) $s->quantity_on_hand * (float) $s->average_cost, 2),
            ])->sortByDesc('value')->values();

        return Inertia::render('Inventory/Warehouses/Show', [
            'warehouse' => [
                'id' => $warehouse->id,
                'code' => $warehouse->code,
                'name' => $warehouse->name,
                'type' => $warehouse->type,
                'address_line1' => $warehouse->address_line1,
                'city' => $warehouse->city,
                'state' => $warehouse->state,
                'postal_code' => $warehouse->postal_code,
                'country' => $warehouse->country,
                'is_default' => $warehouse->is_default,
                'is_active' => $warehouse->is_active,
                'notes' => $warehouse->notes,
                'value' => round((float) $stock->sum('value'), 2),
            ],
            'locations' => $warehouse->locations->map(fn (Location $l) => [
                'id' => $l->id,
                'code' => $l->code,
                'name' => $l->name,
                'zone' => $l->zone,
                'aisle' => $l->aisle,
                'rack' => $l->rack,
                'shelf' => $l->shelf,
                'bin' => $l->bin,
                'type' => $l->type,
                'is_active' => $l->is_active,
            ]),
            'stock' => $stock,
            'can' => ['manage' => $request->user()->can('manage warehouses')],
        ]);
    }

    public function store(WarehouseRequest $request): RedirectResponse
    {
        $this->authorize('create', Warehouse::class);
        $user = $request->user();

        $data = $request->validated();
        $warehouse = DB::transaction(function () use ($data, $user) {
            if (! empty($data['is_default'])) {
                Warehouse::where('organization_id', $user->organization_id)->update(['is_default' => false]);
            }

            return Warehouse::create([
                ...$data,
                'organization_id' => $user->organization_id,
                'created_by' => $user->id,
            ]);
        });

        return redirect()->route('inventory.warehouses.show', $warehouse)->with('success', 'Warehouse created.');
    }

    public function update(WarehouseRequest $request, Warehouse $warehouse): RedirectResponse
    {
        $this->authorize('update', $warehouse);
        $data = $request->validated();

        DB::transaction(function () use ($data, $warehouse) {
            if (! empty($data['is_default'])) {
                Warehouse::where('organization_id', $warehouse->organization_id)
                    ->where('id', '!=', $warehouse->id)->update(['is_default' => false]);
            }
            $warehouse->update($data);
        });

        return back()->with('success', 'Warehouse updated.');
    }

    public function destroy(Request $request, Warehouse $warehouse): RedirectResponse
    {
        $this->authorize('delete', $warehouse);

        $hasStock = $warehouse->stocks()->where('quantity_on_hand', '!=', 0)->exists();
        if ($hasStock) {
            return back()->with('error', 'Cannot delete a warehouse that still holds stock. Transfer or zero it out first.');
        }

        $warehouse->delete();

        return redirect()->route('inventory.warehouses.index')->with('success', "Warehouse \"{$warehouse->name}\" deleted.");
    }

    public function storeLocation(Request $request, Warehouse $warehouse): RedirectResponse
    {
        $this->authorize('update', $warehouse);
        $data = $this->validateLocation($request, $warehouse);

        $warehouse->locations()->create([
            ...$data,
            'organization_id' => $warehouse->organization_id,
        ]);

        return back()->with('success', 'Location added.');
    }

    public function updateLocation(Request $request, Warehouse $warehouse, Location $location): RedirectResponse
    {
        $this->authorize('update', $warehouse);
        abort_unless($location->inventory_warehouse_id === $warehouse->id, 404);

        $location->update($this->validateLocation($request, $warehouse, $location));

        return back()->with('success', 'Location updated.');
    }

    public function destroyLocation(Request $request, Warehouse $warehouse, Location $location): RedirectResponse
    {
        $this->authorize('update', $warehouse);
        abort_unless($location->inventory_warehouse_id === $warehouse->id, 404);

        $location->delete();

        return back()->with('success', 'Location removed.');
    }

    /** @return array<string,mixed> */
    private function validateLocation(Request $request, Warehouse $warehouse, ?Location $location = null): array
    {
        return $request->validate([
            'code' => [
                'required', 'string', 'max:60',
                \Illuminate\Validation\Rule::unique('inventory_locations', 'code')
                    ->where('inventory_warehouse_id', $warehouse->id)
                    ->whereNull('deleted_at')
                    ->ignore($location?->id),
            ],
            'name' => ['nullable', 'string', 'max:120'],
            'zone' => ['nullable', 'string', 'max:40'],
            'aisle' => ['nullable', 'string', 'max:40'],
            'rack' => ['nullable', 'string', 'max:40'],
            'shelf' => ['nullable', 'string', 'max:40'],
            'bin' => ['nullable', 'string', 'max:40'],
            'type' => ['nullable', 'string', 'in:bin,staging,receiving,shipping,quarantine'],
            'is_active' => ['boolean'],
        ]);
    }
}
