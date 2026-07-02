<?php

namespace App\Modules\Procurement\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use App\Modules\Inventory\Models\Product;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Procurement\Enums\ApprovalStatus;
use App\Modules\Procurement\Enums\PurchaseOrderStatus;
use App\Modules\Procurement\Enums\SupplierStatus;
use App\Modules\Procurement\Http\Requests\PurchaseOrderRequest;
use App\Modules\Procurement\Http\Requests\SendDocumentRequest;
use App\Modules\Procurement\Models\PurchaseOrder;
use App\Modules\Procurement\Models\PurchaseOrderItem;
use App\Modules\Procurement\Models\Supplier;
use App\Modules\Procurement\Services\ApprovalService;
use App\Modules\Procurement\Services\ProcurementDocumentService;
use App\Modules\Procurement\Services\ProcurementNumberService;
use App\Modules\Procurement\Services\PurchaseOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
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

    /** Edit form for a draft purchase order (prefilled). */
    public function edit(Request $request, PurchaseOrder $purchaseOrder): Response|RedirectResponse
    {
        $this->authorize('update', $purchaseOrder);

        if (! $purchaseOrder->status->isEditable()) {
            return redirect()->route('procurement.purchase-orders.show', $purchaseOrder)
                ->with('error', 'Only draft purchase orders can be edited.');
        }

        $purchaseOrder->load(['items' => fn ($q) => $q->orderBy('position')->orderBy('id')]);

        return Inertia::render('Procurement/PurchaseOrders/Edit', array_merge($this->formData($request), [
            'order' => [
                'id' => $purchaseOrder->id,
                'number' => $purchaseOrder->number,
                'procurement_supplier_id' => $purchaseOrder->procurement_supplier_id ? (string) $purchaseOrder->procurement_supplier_id : '',
                'company_id' => $purchaseOrder->company_id ? (string) $purchaseOrder->company_id : '',
                'inventory_warehouse_id' => $purchaseOrder->inventory_warehouse_id ? (string) $purchaseOrder->inventory_warehouse_id : '',
                'order_date' => $purchaseOrder->order_date?->toDateString(),
                'expected_date' => $purchaseOrder->expected_date?->toDateString(),
                'currency' => $purchaseOrder->currency,
                'tax_rate' => (float) $purchaseOrder->tax_rate,
                'tax_amount' => (float) $purchaseOrder->tax_amount,
                'shipping_amount' => (float) $purchaseOrder->shipping_amount,
                'notes' => $purchaseOrder->notes,
                'payment_terms' => $purchaseOrder->payment_terms,
                'shipping_terms' => $purchaseOrder->shipping_terms,
                'use_ql_shipping_account' => (bool) $purchaseOrder->use_ql_shipping_account,
                'items' => $purchaseOrder->items->map(fn (PurchaseOrderItem $i) => [
                    'inventory_product_id' => $i->inventory_product_id ? (string) $i->inventory_product_id : '',
                    'description' => $i->description,
                    'sku' => $i->sku,
                    'quantity_ordered' => (string) (float) $i->quantity_ordered,
                    'unit_cost' => (string) (float) $i->unit_cost,
                ]),
            ],
        ]));
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
                'company_id' => $data['company_id'] ?? null,
                'inventory_warehouse_id' => $data['inventory_warehouse_id'] ?? null,
                'number' => $this->numbers->generate($user->organization_id),
                'status' => PurchaseOrderStatus::Draft,
                'order_date' => $data['order_date'] ?? now()->toDateString(),
                'expected_date' => $data['expected_date'] ?? null,
                'currency' => $data['currency'],
                'tax_rate' => $data['tax_rate'],
                'tax_amount' => $data['tax_amount'] ?? 0,
                'shipping_amount' => $data['shipping_amount'],
                'notes' => $data['notes'] ?? null,
                'payment_terms' => $data['payment_terms'] ?? null,
                'shipping_terms' => $data['shipping_terms'] ?? null,
                'use_ql_shipping_account' => $data['use_ql_shipping_account'] ?? false,
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

    public function show(Request $request, PurchaseOrder $purchaseOrder, ProcurementDocumentService $docs): Response
    {
        $this->authorize('view', $purchaseOrder);

        $purchaseOrder->load([
            'supplier:id,name,code,email,payment_terms',
            'company:id,name',
            'warehouse:id,name,code',
            'approver:id,name',
            'items' => fn ($q) => $q->orderBy('position')->orderBy('id'),
            'items.product:id,sku,name',
            'attachments.uploader:id,name',
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
                'client' => $purchaseOrder->company ? ['id' => $purchaseOrder->company->id, 'name' => $purchaseOrder->company->name] : null,
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
                'payment_terms' => $purchaseOrder->payment_terms,
                'shipping_terms' => $purchaseOrder->shipping_terms,
                'use_ql_shipping_account' => (bool) $purchaseOrder->use_ql_shipping_account,
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
                'createBill' => $request->user()->can('manage bills'),
            ],
            'statuses' => collect(PurchaseOrderStatus::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()]),
            'bills' => $purchaseOrder->bills()->orderByDesc('id')->get(['id', 'number', 'payment_status', 'total', 'currency'])
                ->map(fn ($b) => [
                    'id' => $b->id, 'number' => $b->number,
                    'payment_status' => $b->payment_status->value,
                    'payment_status_label' => $b->payment_status->label(),
                    'payment_status_color' => $b->payment_status->color(),
                    'total' => (float) $b->total, 'currency' => $b->currency,
                ]),
            'send' => array_merge($docs->sendMeta($purchaseOrder), [
                'pdf_url' => route('procurement.purchase-orders.pdf', $purchaseOrder),
                'send_url' => route('procurement.purchase-orders.send-email', $purchaseOrder),
                'emailed_at' => $purchaseOrder->emailed_at?->toIso8601String(),
            ]),
            'attachments' => AttachmentController::serialize($purchaseOrder),
            'approval' => ApprovalController::serialize($purchaseOrder->latestApproval(), $request->user()),
        ]);
    }

    /** Stream the branded PO PDF inline (view/download in the browser). */
    public function pdf(Request $request, PurchaseOrder $purchaseOrder, ProcurementDocumentService $docs)
    {
        $this->authorize('view', $purchaseOrder);

        return response($docs->pdf($purchaseOrder), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$docs->filename($purchaseOrder).'"',
        ]);
    }

    /** Email the PO (as a PDF, with a covering message) to the supplier. */
    public function sendEmail(SendDocumentRequest $request, PurchaseOrder $purchaseOrder, ProcurementDocumentService $docs): RedirectResponse
    {
        $this->authorize('update', $purchaseOrder);

        try {
            $docs->sendEmail($purchaseOrder, $request->validated());
        } catch (\Throwable $e) {
            Log::warning('Purchase order send-email failed', ['po' => $purchaseOrder->number, 'error' => $e->getMessage()]);

            return back()->with('error', 'Could not send the email. '.$e->getMessage());
        }

        $this->service->markEmailed($purchaseOrder);

        return back()->with('success', "Purchase order {$purchaseOrder->number} emailed to {$request->validated()['to']}.");
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
                'company_id' => $data['company_id'] ?? null,
                'inventory_warehouse_id' => $data['inventory_warehouse_id'] ?? null,
                'order_date' => $data['order_date'] ?? $purchaseOrder->order_date,
                'expected_date' => $data['expected_date'] ?? null,
                'currency' => $data['currency'],
                'tax_rate' => $data['tax_rate'],
                'tax_amount' => $data['tax_amount'] ?? 0,
                'shipping_amount' => $data['shipping_amount'],
                'notes' => $data['notes'] ?? null,
                'payment_terms' => $data['payment_terms'] ?? null,
                'shipping_terms' => $data['shipping_terms'] ?? null,
                'use_ql_shipping_account' => $data['use_ql_shipping_account'] ?? false,
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

    public function submit(Request $request, PurchaseOrder $purchaseOrder, ApprovalService $approvals): RedirectResponse
    {
        $this->authorize('update', $purchaseOrder);
        $this->service->submit($purchaseOrder);
        // Instantiate a multi-level chain if one is configured; else simple approve governs.
        $approvals->start($purchaseOrder, $request->user()->id);

        return back()->with('success', 'Submitted for approval.');
    }

    public function approve(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorize('approve', $purchaseOrder);
        if ($purchaseOrder->latestApproval()?->status === ApprovalStatus::Pending) {
            return back()->with('error', 'This purchase order is in a multi-level approval chain — use the approval panel.');
        }
        $this->service->approve($purchaseOrder, $request->user()->id);

        return back()->with('success', "Purchase order {$purchaseOrder->number} approved.");
    }

    public function markSent(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorize('update', $purchaseOrder);
        $po = $this->service->markSent($purchaseOrder);

        return back()->with('success', "Purchase order {$po->number} marked as sent.");
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

    /**
     * Free-form status override — set the PO to any status at any stage. This
     * bypasses the lifecycle guards on purpose (managers can force delivered,
     * confirmed, cancelled, back to draft…). Inventory receipts are unaffected.
     */
    public function setStatus(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorize('update', $purchaseOrder);

        $validated = $request->validate([
            'status' => ['required', Rule::enum(PurchaseOrderStatus::class)],
        ]);

        $status = PurchaseOrderStatus::from($validated['status']);
        $this->service->setStatus($purchaseOrder, $status, $request->user()->id);

        return back()->with('success', "Status set to {$status->label()}.");
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
    /** Raise a draft purchase order for a vendor by copying a CRM sales invoice/estimate. */
    public function storeFromInvoice(Request $request, \App\Models\Crm\Invoice $invoice): RedirectResponse
    {
        $this->authorize('create', PurchaseOrder::class);
        abort_unless($invoice->organization_id === $request->user()->organization_id, 404);

        $validated = $request->validate([
            'procurement_supplier_id' => ['required', \Illuminate\Validation\Rule::exists('procurement_suppliers', 'id')
                ->where('organization_id', $request->user()->organization_id)],
        ]);

        $po = $this->service->fromCrmInvoice($invoice, (int) $validated['procurement_supplier_id'], $request->user()->id);

        return redirect()->route('procurement.purchase-orders.show', $po)
            ->with('success', "Draft purchase order created from {$invoice->number}.");
    }

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
            'companies' => Company::where('organization_id', $orgId)->orderBy('name')->get(['id', 'name']),
            'sourceInvoices' => PurchaseRequestController::sourceInvoices($orgId),
        ];
    }
}
