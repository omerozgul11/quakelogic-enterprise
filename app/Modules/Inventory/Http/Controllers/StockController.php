<?php

namespace App\Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Exceptions\InsufficientStockException;
use App\Modules\Inventory\Models\Product;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Services\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Stock-changing actions for a product. Thin: validates, resolves the
 * org-scoped warehouse, and delegates to InventoryService (the sole owner of
 * stock mutation). All quantity/cost math lives in the service.
 */
class StockController extends Controller
{
    public function __construct(private readonly InventoryService $inventory) {}

    public function receive(Request $request, Product $product): RedirectResponse
    {
        $this->authorize('adjustStock', $product);
        $data = $this->validateMovement($request, withCost: true);

        $this->inventory->receive(
            $product,
            $this->warehouse($request, $data['warehouse_id']),
            (float) $data['quantity'],
            (float) ($data['unit_cost'] ?? 0),
            $this->opts($request, $data),
        );

        return back()->with('success', 'Stock received.');
    }

    public function issue(Request $request, Product $product): RedirectResponse
    {
        $this->authorize('adjustStock', $product);
        $data = $this->validateMovement($request);

        return $this->guarded(fn () => $this->inventory->issue(
            $product,
            $this->warehouse($request, $data['warehouse_id']),
            (float) $data['quantity'],
            $this->opts($request, $data),
        ), 'Stock issued.');
    }

    public function adjust(Request $request, Product $product): RedirectResponse
    {
        $this->authorize('adjustStock', $product);
        $data = $request->validate([
            'warehouse_id' => $this->warehouseRule($request),
            'delta' => ['required', 'numeric', 'not_in:0'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        return $this->guarded(fn () => $this->inventory->adjust(
            $product,
            $this->warehouse($request, $data['warehouse_id']),
            (float) $data['delta'],
            $this->opts($request, $data),
        ), 'Stock adjusted.');
    }

    public function count(Request $request, Product $product): RedirectResponse
    {
        $this->authorize('adjustStock', $product);
        $data = $request->validate([
            'warehouse_id' => $this->warehouseRule($request),
            'counted_quantity' => ['required', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $this->inventory->count(
            $product,
            $this->warehouse($request, $data['warehouse_id']),
            (float) $data['counted_quantity'],
            $this->opts($request, $data),
        );

        return back()->with('success', 'Count recorded.');
    }

    public function transfer(Request $request, Product $product): RedirectResponse
    {
        $this->authorize('adjustStock', $product);
        $orgId = $request->user()->organization_id;
        $data = $request->validate([
            'from_warehouse_id' => ['required', Rule::exists('inventory_warehouses', 'id')->where('organization_id', $orgId)],
            'to_warehouse_id' => ['required', 'different:from_warehouse_id', Rule::exists('inventory_warehouses', 'id')->where('organization_id', $orgId)],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        return $this->guarded(fn () => $this->inventory->transfer(
            $product,
            Warehouse::where('organization_id', $orgId)->findOrFail($data['from_warehouse_id']),
            Warehouse::where('organization_id', $orgId)->findOrFail($data['to_warehouse_id']),
            (float) $data['quantity'],
            $this->opts($request, $data),
        ), 'Stock transferred.');
    }

    /** Run a stock op, converting an out-of-stock failure into a friendly error. */
    private function guarded(callable $op, string $success): RedirectResponse
    {
        try {
            $op();
        } catch (InsufficientStockException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $success);
    }

    /** @return array<string,mixed> */
    private function validateMovement(Request $request, bool $withCost = false): array
    {
        return $request->validate([
            'warehouse_id' => $this->warehouseRule($request),
            'quantity' => ['required', 'numeric', 'gt:0'],
            'unit_cost' => $withCost ? ['nullable', 'numeric', 'min:0'] : ['prohibited'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);
    }

    private function warehouseRule(Request $request): array
    {
        return ['required', Rule::exists('inventory_warehouses', 'id')->where('organization_id', $request->user()->organization_id)];
    }

    private function warehouse(Request $request, int|string $id): Warehouse
    {
        return Warehouse::where('organization_id', $request->user()->organization_id)->findOrFail($id);
    }

    /** @return array<string,mixed> */
    private function opts(Request $request, array $data): array
    {
        return [
            'actor_id' => $request->user()->id,
            'note' => $data['note'] ?? null,
        ];
    }
}
