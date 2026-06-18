<?php

namespace App\Modules\Manufacturing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Exceptions\InsufficientStockException;
use App\Modules\Inventory\Models\Product;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Manufacturing\Enums\BomStatus;
use App\Modules\Manufacturing\Enums\WorkOrderStatus;
use App\Modules\Manufacturing\Http\Requests\WorkOrderRequest;
use App\Modules\Manufacturing\Models\Bom;
use App\Modules\Manufacturing\Models\WorkOrder;
use App\Modules\Manufacturing\Services\ManufacturingNumberService;
use App\Modules\Manufacturing\Services\WorkOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class WorkOrderController extends Controller
{
    public function __construct(
        private readonly WorkOrderService $service,
        private readonly ManufacturingNumberService $numbers,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', WorkOrder::class);
        $orgId = $request->user()->organization_id;

        $orders = WorkOrder::where('organization_id', $orgId)
            ->with('product:id,sku,name')
            ->when($request->search, fn ($q, $s) => $q->where(fn ($w) => $w
                ->where('number', 'like', "%{$s}%")
                ->orWhereHas('product', fn ($p) => $p->where('sku', 'like', "%{$s}%")->orWhere('name', 'like', "%{$s}%"))))
            ->when($request->status, fn ($q, $st) => $q->where('status', $st))
            ->latest('id')
            ->paginate(20)->withQueryString()
            ->through(fn (WorkOrder $w) => [
                'id' => $w->id,
                'number' => $w->number,
                'product' => $w->product ? $w->product->sku.' · '.$w->product->name : '—',
                'status' => $w->status->value,
                'status_label' => $w->status->label(),
                'status_color' => $w->status->color(),
                'quantity_planned' => (float) $w->quantity_planned,
                'quantity_produced' => (float) $w->quantity_produced,
                'scheduled_date' => $w->scheduled_date?->toDateString(),
            ]);

        return Inertia::render('Manufacturing/WorkOrders/Index', [
            'orders' => $orders,
            'filters' => $request->only(['search', 'status']),
            'statuses' => collect(WorkOrderStatus::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()]),
            'can' => ['manage' => $request->user()->can('manage work orders')],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', WorkOrder::class);

        return Inertia::render('Manufacturing/WorkOrders/Create', $this->formData($request));
    }

    public function store(WorkOrderRequest $request): RedirectResponse
    {
        $this->authorize('create', WorkOrder::class);
        $user = $request->user();
        $data = $request->validated();

        // Default to the product's active/default BOM when none was chosen.
        $bomId = $data['manufacturing_bom_id'] ?? Bom::where('organization_id', $user->organization_id)
            ->where('inventory_product_id', $data['inventory_product_id'])
            ->where('status', BomStatus::Active->value)
            ->orderByDesc('is_default')->value('id');

        $wo = WorkOrder::create([
            'organization_id' => $user->organization_id,
            'created_by' => $user->id,
            'inventory_product_id' => $data['inventory_product_id'],
            'manufacturing_bom_id' => $bomId,
            'inventory_warehouse_id' => $data['inventory_warehouse_id'],
            'number' => $this->numbers->generate($user->organization_id),
            'status' => WorkOrderStatus::Draft,
            'quantity_planned' => $data['quantity_planned'],
            'scheduled_date' => $data['scheduled_date'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        return redirect()->route('manufacturing.work-orders.show', $wo)->with('success', "Work order {$wo->number} created.");
    }

    public function show(Request $request, WorkOrder $workOrder): Response
    {
        $this->authorize('view', $workOrder);

        $workOrder->load(['product:id,sku,name,unit_of_measure', 'warehouse:id,name', 'bom:id,name,version', 'creator:id,name']);

        return Inertia::render('Manufacturing/WorkOrders/Show', [
            'order' => [
                'id' => $workOrder->id,
                'number' => $workOrder->number,
                'status' => $workOrder->status->value,
                'status_label' => $workOrder->status->label(),
                'status_color' => $workOrder->status->color(),
                'can_complete' => $workOrder->status->canComplete(),
                'product' => ['id' => $workOrder->product?->id, 'sku' => $workOrder->product?->sku, 'name' => $workOrder->product?->name, 'unit_of_measure' => $workOrder->product?->unit_of_measure],
                'warehouse' => ['id' => $workOrder->warehouse?->id, 'name' => $workOrder->warehouse?->name],
                'bom' => $workOrder->bom ? ['id' => $workOrder->bom->id, 'name' => $workOrder->bom->name, 'version' => $workOrder->bom->version] : null,
                'quantity_planned' => (float) $workOrder->quantity_planned,
                'quantity_produced' => (float) $workOrder->quantity_produced,
                'build_cost' => (float) $workOrder->build_cost,
                'scheduled_date' => $workOrder->scheduled_date?->toDateString(),
                'completed_at' => $workOrder->completed_at?->toIso8601String(),
                'notes' => $workOrder->notes,
            ],
            'requirements' => $this->service->requirements($workOrder),
            'can' => [
                'manage' => $request->user()->can('manage work orders'),
                'complete' => $request->user()->can('complete work orders'),
            ],
        ]);
    }

    public function update(WorkOrderRequest $request, WorkOrder $workOrder): RedirectResponse
    {
        $this->authorize('update', $workOrder);

        if (! $workOrder->status->isEditable()) {
            return back()->with('error', 'Only draft work orders can be edited.');
        }

        $data = $request->validated();
        $workOrder->update([
            'inventory_product_id' => $data['inventory_product_id'],
            'manufacturing_bom_id' => $data['manufacturing_bom_id'] ?? $workOrder->manufacturing_bom_id,
            'inventory_warehouse_id' => $data['inventory_warehouse_id'],
            'quantity_planned' => $data['quantity_planned'],
            'scheduled_date' => $data['scheduled_date'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        return back()->with('success', 'Work order updated.');
    }

    public function destroy(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        $this->authorize('delete', $workOrder);

        if ((float) $workOrder->quantity_produced > 0) {
            return back()->with('error', 'Cannot delete a work order that has produced output. Cancel it instead.');
        }

        $number = $workOrder->number;
        $workOrder->delete();

        return redirect()->route('manufacturing.work-orders.index')->with('success', "Work order {$number} deleted.");
    }

    public function release(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        $this->authorize('update', $workOrder);
        $this->service->release($workOrder);

        return back()->with('success', 'Work order released.');
    }

    public function start(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        $this->authorize('update', $workOrder);
        $this->service->start($workOrder);

        return back()->with('success', 'Work order started.');
    }

    public function complete(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        $this->authorize('complete', $workOrder);
        $validated = $request->validate(['quantity' => ['nullable', 'numeric', 'gt:0']]);

        try {
            $this->service->complete($workOrder, $validated['quantity'] ?? null, ['actor_id' => $request->user()->id]);
        } catch (InsufficientStockException|RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Work order {$workOrder->number} completed — finished goods added to stock.");
    }

    public function cancel(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        $this->authorize('update', $workOrder);
        try {
            $this->service->cancel($workOrder);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Work order cancelled.');
    }

    /** @return array<string,mixed> */
    private function formData(Request $request): array
    {
        $orgId = $request->user()->organization_id;

        return [
            'products' => Product::where('organization_id', $orgId)->where('is_active', true)->orderBy('name')->get(['id', 'sku', 'name']),
            'warehouses' => Warehouse::where('organization_id', $orgId)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']),
            'boms' => Bom::where('organization_id', $orgId)->where('status', BomStatus::Active->value)
                ->orderByDesc('is_default')->get(['id', 'name', 'version', 'inventory_product_id']),
        ];
    }
}
