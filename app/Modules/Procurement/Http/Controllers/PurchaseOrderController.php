<?php

namespace App\Modules\Procurement\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Inventory\Models\Product;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Procurement\Enums\PurchaseOrderStatus;
use App\Modules\Procurement\Enums\SupplierStatus;
use App\Modules\Procurement\Http\Requests\PurchaseOrderRequest;
use App\Modules\Procurement\Models\PurchaseOrder;
use App\Modules\Procurement\Models\PurchaseOrderItem;
use App\Modules\Procurement\Models\Supplier;
use App\Modules\Procurement\Services\ProcurementNumberService;
use App\Modules\Procurement\Services\PurchaseOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class PurchaseOrderController extends Controller
{
    public function __construct(
        private readonly PurchaseOrderService $service,
        private readonly ProcurementNumberService $numbers,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', PurchaseOrder::class);
        $orgId = $request->user()->organization_id;

        $orders = PurchaseOrder::where('organization_id', $orgId)
            ->with('supplier:id,name')
            ->when($request->search, fn ($q, $s) => $q->where(fn ($w) => $w
                ->where('number', 'like', "%{$s}%")
                ->orWhereHas('supplier', fn ($sup) => $sup->where('name', 'like', "%{$s}%"))))
            ->when($request->status, fn ($q, $st) => $q->where('status', $st))
            ->latest('id')
            ->paginate(20)->withQueryString()
            ->through(fn (PurchaseOrder $po) => [
                'id' => $po->id,
                'number' => $po->number,
                'supplier' => $po->supplier?->name,
                'status' => $po->status->value,
                'status_label' => $po->status->label(),
                'status_color' => $po->status->color(),
                'total' => (float) $po->total,
                'currency' => $po->currency,
                'order_date' => $po->order_date?->toDateString(),
                'expected_date' => $po->expected_date?->toDateString(),
            ]);

        return Inertia::render('Procurement/PurchaseOrders/Index', [
            'orders' => $orders,
            'filters' => $request->only(['search', 'status']),
            'statuses' => collect(PurchaseOrderStatus::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()]),
            'can' => ['manage' => $request->user()->can('manage purchase orders')],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', PurchaseOrder::class);

        return Inertia::render('Procurement/PurchaseOrders/Create', $this->formData($request));
    }

    public function store(PurchaseOrderRequest $request): RedirectResponse
    {
        $this->authorize('create', PurchaseOrder::class);
        $user = $request->user();
        $data = $request->validated();

        $po = DB::transaction(function () use ($data, $user) {
            $supplierId = $this->resolveSupplierId($data, $user);
            $po = PurchaseOrder::create([
                'organization_id' => $user->organization_id,
                'created_by' => $user->id,
                'procurement_supplier_id' => $supplierId,
                'inventory_warehouse_id' => $data['inventory_warehouse_id'] ?? null,
                'number' => $this->numbers->generate($user->organization_id),
                'status' => PurchaseOrderStatus::Draft,
                'order_date' => $data['order_date'] ?? now()->toDateString(),
                'expected_date' => $data['expected_date'] ?? null,
                'currency' => $data['currency'],
                'tax_rate' => $data['tax_rate'],
                'shipping_amount' => $data['shipping_amount'],
                'notes' => $data['notes'] ?? null,
            ]);

            $this->syncItems($po, $data['items']);
            $this->service->recalcTotals($po);

            return $po;
        });

        // Email the supplier a copy and confirm to the internal buyer. Runs
        // after commit so a mail hiccup can't roll back the created PO.
        $this->service->notifyCreated($po);

        return redirect()->route('procurement.purchase-orders.show', $po)->with('success', "Purchase order {$po->number} created.");
    }

    public function show(Request $request, PurchaseOrder $purchaseOrder): Response
    {
        $this->authorize('view', $purchaseOrder);

        $purchaseOrder->load([
            'supplier:id,name,code,email,payment_terms',
            'warehouse:id,name,code',
            'approver:id,name',
            'items' => fn ($q) => $q->orderBy('position')->orderBy('id'),
            'items.product:id,sku,name',
        ]);

        return Inertia::render('Procurement/PurchaseOrders/Show', [
            'order' => [
                'id' => $purchaseOrder->id,
                'number' => $purchaseOrder->number,
                'status' => $purchaseOrder->status->value,
                'status_label' => $purchaseOrder->status->label(),
                'status_color' => $purchaseOrder->status->color(),
                'is_editable' => $purchaseOrder->status->isEditable(),
                'can_receive' => $purchaseOrder->status->canReceive(),
                'supplier' => ['id' => $purchaseOrder->supplier?->id, 'name' => $purchaseOrder->supplier?->name, 'code' => $purchaseOrder->supplier?->code, 'payment_terms' => $purchaseOrder->supplier?->payment_terms],
                'warehouse' => $purchaseOrder->warehouse ? ['id' => $purchaseOrder->warehouse->id, 'name' => $purchaseOrder->warehouse->name] : null,
                'order_date' => $purchaseOrder->order_date?->toDateString(),
                'expected_date' => $purchaseOrder->expected_date?->toDateString(),
                'currency' => $purchaseOrder->currency,
                'subtotal' => (float) $purchaseOrder->subtotal,
                'tax_rate' => (float) $purchaseOrder->tax_rate,
                'tax_amount' => (float) $purchaseOrder->tax_amount,
                'shipping_amount' => (float) $purchaseOrder->shipping_amount,
                'total' => (float) $purchaseOrder->total,
                'notes' => $purchaseOrder->notes,
                'approved_by' => $purchaseOrder->approver?->name,
                'approved_at' => $purchaseOrder->approved_at?->toIso8601String(),
                'items' => $purchaseOrder->items->map(fn (PurchaseOrderItem $i) => [
                    'id' => $i->id,
                    'description' => $i->description,
                    'sku' => $i->sku ?? $i->product?->sku,
                    'product_id' => $i->inventory_product_id,
                    'product' => $i->product?->name,
                    'quantity_ordered' => (float) $i->quantity_ordered,
                    'quantity_received' => (float) $i->quantity_received,
                    'outstanding' => $i->outstanding(),
                    'unit_cost' => (float) $i->unit_cost,
                    'line_total' => (float) $i->line_total,
                ]),
            ],
            'can' => [
                'manage' => $request->user()->can('manage purchase orders'),
                'approve' => $request->user()->can('approve purchase orders'),
                'receive' => $request->user()->can('receive goods'),
            ],
        ]);
    }

    public function update(PurchaseOrderRequest $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorize('update', $purchaseOrder);

        if (! $purchaseOrder->status->isEditable()) {
            return back()->with('error', 'Only draft purchase orders can be edited.');
        }

        $data = $request->validated();
        DB::transaction(function () use ($purchaseOrder, $data) {
            $purchaseOrder->update([
                'procurement_supplier_id' => $data['procurement_supplier_id'],
                'inventory_warehouse_id' => $data['inventory_warehouse_id'] ?? null,
                'order_date' => $data['order_date'] ?? $purchaseOrder->order_date,
                'expected_date' => $data['expected_date'] ?? null,
                'currency' => $data['currency'],
                'tax_rate' => $data['tax_rate'],
                'shipping_amount' => $data['shipping_amount'],
                'notes' => $data['notes'] ?? null,
            ]);

            $purchaseOrder->items()->delete();
            $this->syncItems($purchaseOrder, $data['items']);
            $this->service->recalcTotals($purchaseOrder);
        });

        return back()->with('success', 'Purchase order updated.');
    }

    public function destroy(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorize('delete', $purchaseOrder);

        if ((float) $purchaseOrder->items()->sum('quantity_received') > 0) {
            return back()->with('error', 'Cannot delete a purchase order that has received stock. Cancel it instead.');
        }

        $number = $purchaseOrder->number;
        $purchaseOrder->delete();

        return redirect()->route('procurement.purchase-orders.index')->with('success', "Purchase order {$number} deleted.");
    }

    public function submit(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorize('update', $purchaseOrder);
        $this->service->submit($purchaseOrder);

        return back()->with('success', 'Submitted for approval.');
    }

    public function approve(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorize('approve', $purchaseOrder);
        $this->service->approve($purchaseOrder, $request->user()->id);

        return back()->with('success', "Purchase order {$purchaseOrder->number} approved.");
    }

    public function markSent(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorize('update', $purchaseOrder);
        $po = $this->service->markSent($purchaseOrder);

        $message = $po->emailed_at
            ? "Purchase order {$po->number} sent and emailed to the supplier."
            : "Purchase order {$po->number} marked as sent — no supplier email on file.";

        return back()->with('success', $message);
    }

    public function cancel(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorize('update', $purchaseOrder);
        try {
            $this->service->cancel($purchaseOrder);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Purchase order cancelled.');
    }

    public function receive(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorize('receive', $purchaseOrder);

        $validated = $request->validate([
            'receive_all' => ['sometimes', 'boolean'],
            'lines' => ['sometimes', 'array'],
            'lines.*.id' => ['required_with:lines', 'integer'],
            'lines.*.quantity' => ['required_with:lines', 'numeric', 'gt:0'],
        ]);

        try {
            if (! empty($validated['receive_all'])) {
                $this->service->receiveAll($purchaseOrder, ['actor_id' => $request->user()->id]);
            } else {
                foreach ($validated['lines'] ?? [] as $line) {
                    $item = $purchaseOrder->items()->whereKey($line['id'])->first();
                    abort_unless($item, 404);
                    $this->service->receiveItem($item, (float) $line['quantity'], ['actor_id' => $request->user()->id]);
                }
            }
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Goods received.');
    }

    /**
     * The supplier id for the PO: an existing selection, or a brand-new supplier
     * created inline from the "supplier doesn't exist yet" details on the form.
     * Runs inside the create transaction so the supplier and PO commit together.
     */
    private function resolveSupplierId(array $data, User $user): int
    {
        if (! empty($data['procurement_supplier_id'])) {
            return (int) $data['procurement_supplier_id'];
        }

        $ns = $data['new_supplier'] ?? [];

        $supplier = Supplier::create([
            'organization_id' => $user->organization_id,
            'created_by' => $user->id,
            'owner_id' => $user->id,
            'name' => $ns['name'],
            'code' => $this->uniqueSupplierCode($ns['code'] ?? null, $ns['name'], $user->organization_id),
            'category' => $ns['category'] ?? null,
            'status' => SupplierStatus::Active,
            'email' => $ns['email'] ?? null,
            'phone' => $ns['phone'] ?? null,
            'website' => $ns['website'] ?? null,
            'address_line1' => $ns['address_line1'] ?? null,
            'city' => $ns['city'] ?? null,
            'state' => $ns['state'] ?? null,
            'postal_code' => $ns['postal_code'] ?? null,
            'country' => $ns['country'] ?? null,
            'payment_terms' => $ns['payment_terms'] ?? null,
            'currency' => $data['currency'] ?? 'USD',
            'tax_id' => $ns['tax_id'] ?? null,
            'notes' => $ns['notes'] ?? null,
        ]);

        return $supplier->id;
    }

    /** A supplier code unique within the org — the given one, or derived from the name. */
    private function uniqueSupplierCode(?string $provided, string $name, int $orgId): string
    {
        $base = $provided !== null && trim($provided) !== ''
            ? strtoupper(trim($provided))
            : strtoupper(Str::slug($name, ''));
        $base = substr((string) preg_replace('/[^A-Z0-9]/', '', $base), 0, 12);
        if ($base === '') {
            $base = 'SUP';
        }

        $code = $base;
        $n = 1;
        while (Supplier::withTrashed()->where('organization_id', $orgId)->where('code', $code)->exists()) {
            $code = $base.'-'.(++$n);
        }

        return $code;
    }

    /** Persist PO line items from validated request data. */
    private function syncItems(PurchaseOrder $po, array $items): void
    {
        foreach (array_values($items) as $position => $line) {
            $po->items()->create([
                'organization_id' => $po->organization_id,
                'inventory_product_id' => $line['inventory_product_id'] ?? null,
                'description' => $line['description'],
                'sku' => $line['sku'] ?? null,
                'quantity_ordered' => $line['quantity_ordered'],
                'unit_cost' => $line['unit_cost'],
                'line_total' => round((float) $line['quantity_ordered'] * (float) $line['unit_cost'], 2),
                'position' => $position,
            ]);
        }
    }

    /** @return array<string,mixed> */
    private function formData(Request $request): array
    {
        $orgId = $request->user()->organization_id;

        return [
            'suppliers' => Supplier::where('organization_id', $orgId)->where('status', 'active')
                ->orderBy('name')->get(['id', 'name', 'code', 'currency']),
            'warehouses' => Warehouse::where('organization_id', $orgId)->where('is_active', true)
                ->orderBy('name')->get(['id', 'name', 'code']),
            'products' => Product::where('organization_id', $orgId)->where('is_active', true)
                ->orderBy('name')->get(['id', 'sku', 'name', 'unit_cost']),
        ];
    }
}
