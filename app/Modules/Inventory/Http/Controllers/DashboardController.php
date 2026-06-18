<?php

namespace App\Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Models\Movement;
use App\Modules\Inventory\Models\Product;
use App\Modules\Inventory\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Product::class);
        $orgId = $request->user()->organization_id;

        $valuation = (float) DB::table('inventory_stocks')
            ->where('organization_id', $orgId)
            ->sum(DB::raw('quantity_on_hand * average_cost'));

        // Products at/below their reorder point (only those with one set).
        $lowStock = Product::where('organization_id', $orgId)
            ->whereNotNull('reorder_point')
            ->withSum('stocks as on_hand', 'quantity_on_hand')
            ->get()
            ->filter(fn (Product $p) => (float) $p->on_hand <= (float) $p->reorder_point)
            ->take(8)
            ->map(fn (Product $p) => [
                'id' => $p->id,
                'sku' => $p->sku,
                'name' => $p->name,
                'on_hand' => (float) $p->on_hand,
                'reorder_point' => (float) $p->reorder_point,
                'unit_of_measure' => $p->unit_of_measure,
            ])->values();

        $recent = Movement::where('organization_id', $orgId)
            ->with(['product:id,sku,name', 'warehouse:id,name'])
            ->latest('occurred_at')->latest('id')
            ->limit(10)->get()
            ->map(fn (Movement $m) => [
                'id' => $m->id,
                'type_label' => $m->type->label(),
                'type_color' => $m->type->color(),
                'quantity' => (float) $m->quantity,
                'product' => $m->product?->sku.' · '.$m->product?->name,
                'warehouse' => $m->warehouse?->name,
                'occurred_at' => $m->occurred_at?->toIso8601String(),
            ]);

        return Inertia::render('Inventory/Dashboard', [
            'stats' => [
                'products' => Product::where('organization_id', $orgId)->count(),
                'active_products' => Product::where('organization_id', $orgId)->where('is_active', true)->count(),
                'warehouses' => Warehouse::where('organization_id', $orgId)->count(),
                'low_stock' => $lowStock->count(),
                'valuation' => round($valuation, 2),
            ],
            'low_stock' => $lowStock,
            'recent_movements' => $recent,
        ]);
    }
}
