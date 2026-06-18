<?php

namespace App\Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Enums\MovementType;
use App\Modules\Inventory\Models\Movement;
use App\Modules\Inventory\Models\Product;
use App\Modules\Inventory\Models\Warehouse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MovementController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Product::class);
        $orgId = $request->user()->organization_id;

        $movements = Movement::where('organization_id', $orgId)
            ->with(['product:id,sku,name', 'warehouse:id,name', 'creator:id,name'])
            ->when($request->type, fn ($q, $t) => $q->where('type', $t))
            ->when($request->warehouse_id, fn ($q, $w) => $q->where('inventory_warehouse_id', $w))
            ->when($request->search, fn ($q, $s) => $q->whereHas('product', fn ($p) => $p
                ->where('sku', 'like', "%{$s}%")->orWhere('name', 'like', "%{$s}%")))
            ->latest('occurred_at')->latest('id')
            ->paginate(40)->withQueryString()
            ->through(fn (Movement $m) => [
                'id' => $m->id,
                'type' => $m->type->value,
                'type_label' => $m->type->label(),
                'type_color' => $m->type->color(),
                'quantity' => (float) $m->quantity,
                'quantity_after' => (float) $m->quantity_after,
                'unit_cost' => $m->unit_cost !== null ? (float) $m->unit_cost : null,
                'product_id' => $m->inventory_product_id,
                'product' => $m->product?->sku.' · '.$m->product?->name,
                'warehouse' => $m->warehouse?->name,
                'note' => $m->note,
                'by' => $m->creator?->name,
                'occurred_at' => $m->occurred_at?->toIso8601String(),
            ]);

        return Inertia::render('Inventory/Movements/Index', [
            'movements' => $movements,
            'filters' => $request->only(['search', 'type', 'warehouse_id']),
            'types' => collect(MovementType::cases())->map(fn ($t) => ['value' => $t->value, 'label' => $t->label()]),
            'warehouses' => Warehouse::where('organization_id', $orgId)->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
