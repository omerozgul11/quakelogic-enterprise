<?php

namespace App\Modules\Procurement\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Models\Product;
use App\Modules\Procurement\Enums\BillPaymentStatus;
use App\Modules\Procurement\Http\Requests\BillRequest;
use App\Modules\Procurement\Models\Bill;
use App\Modules\Procurement\Models\BillItem;
use App\Modules\Procurement\Models\BillPayment;
use App\Modules\Procurement\Models\PurchaseOrder;
use App\Modules\Procurement\Models\Supplier;
use App\Modules\Procurement\Enums\ApprovalStatus;
use App\Modules\Procurement\Services\ApprovalService;
use App\Modules\Procurement\Services\BillService;
use App\Modules\Procurement\Services\ProcurementDocumentService;
use App\Modules\Procurement\Services\ProcurementNumberService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class BillController extends Controller
{
    public function __construct(
        private readonly BillService $service,
        private readonly ProcurementNumberService $numbers,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Bill::class);
        $orgId = $request->user()->organization_id;

        $bills = Bill::where('organization_id', $orgId)
            ->with('supplier:id,name')
            ->when($request->search, fn ($q, $s) => $q->where(fn ($w) => $w
                ->where('number', 'like', "%{$s}%")->orWhere('vendor_invoice_number', 'like', "%{$s}%")
                ->orWhereHas('supplier', fn ($sup) => $sup->where('name', 'like', "%{$s}%"))))
            ->when($request->payment_status, fn ($q, $st) => $q->where('payment_status', $st))
            ->latest('id')
            ->paginate(20)->withQueryString()
            ->through(fn (Bill $b) => [
                'id' => $b->id,
                'number' => $b->number,
                'vendor_invoice_number' => $b->vendor_invoice_number,
                'supplier' => $b->supplier?->name,
                'payment_status' => $b->payment_status->value,
                'payment_status_label' => $b->payment_status->label(),
                'payment_status_color' => $b->payment_status->color(),
                'total' => (float) $b->total,
                'amount_paid' => (float) $b->amount_paid,
                'currency' => $b->currency,
                'bill_date' => $b->bill_date?->toDateString(),
                'due_date' => $b->due_date?->toDateString(),
                'recurring' => (bool) $b->recurring,
            ]);

        return Inertia::render('Procurement/Bills/Index', [
            'bills' => $bills,
            'filters' => $request->only(['search', 'payment_status']),
            'statuses' => BillPaymentStatus::options(),
            'can' => [
                'manage' => $request->user()->can('manage bills'),
                'approve' => $request->user()->can('approve bill payments'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Bill::class);

        return Inertia::render('Procurement/Bills/Create', $this->formData($request));
    }

    public function store(BillRequest $request): RedirectResponse
    {
        $this->authorize('create', Bill::class);
        $user = $request->user();
        $data = $request->validated();

        $bill = DB::transaction(function () use ($data, $user) {
            $bill = Bill::create([
                'organization_id' => $user->organization_id,
                'created_by' => $user->id,
                'procurement_supplier_id' => $data['procurement_supplier_id'],
                'procurement_purchase_order_id' => $data['procurement_purchase_order_id'] ?? null,
                'number' => $this->numbers->bill($user->organization_id),
                'vendor_invoice_number' => $data['vendor_invoice_number'] ?? null,
                'bill_date' => $data['bill_date'] ?? now()->toDateString(),
                'due_date' => $data['due_date'] ?? null,
                'currency' => $data['currency'],
                'shipping_amount' => $data['shipping_amount'] ?? 0,
                'discount_total' => $data['discount_total'] ?? 0,
                'payment_status' => BillPaymentStatus::Unpaid,
                'recurring' => $data['recurring'] ?? false,
                'recurring_frequency' => ($data['recurring'] ?? false) ? ($data['recurring_frequency'] ?? null) : null,
                'recurring_total_cycles' => $data['recurring_total_cycles'] ?? 0,
                'next_recurring_date' => ($data['recurring'] ?? false) ? ($data['next_recurring_date'] ?? null) : null,
                'notes' => $data['notes'] ?? null,
                'terms' => $data['terms'] ?? null,
            ]);

            $this->syncItems($bill, $data['items']);
            $this->service->recalcTotals($bill);

            return $bill;
        });

        return redirect()->route('procurement.bills.show', $bill)->with('success', "Bill {$bill->number} created.");
    }

    /** One-click: raise a bill straight from a purchase order, copying its lines. */
    public function storeFromOrder(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorize('create', Bill::class);
        abort_unless($purchaseOrder->organization_id === $request->user()->organization_id, 403);

        $bill = $this->service->createFromPurchaseOrder($purchaseOrder, $request->user()->id);

        return redirect()->route('procurement.bills.show', $bill)
            ->with('success', "Bill {$bill->number} created from purchase order {$purchaseOrder->number}.");
    }

    public function show(Request $request, Bill $bill): Response
    {
        $this->authorize('view', $bill);

        $bill->load([
            'supplier:id,name,email', 'purchaseOrder:id,number',
            'items' => fn ($q) => $q->orderBy('position')->orderBy('id'),
            'payments' => fn ($q) => $q->orderByDesc('paid_on')->orderByDesc('id'),
            'payments.recorder:id,name', 'payments.approver:id,name',
            'attachments.uploader:id,name',
        ]);

        $user = $request->user();

        return Inertia::render('Procurement/Bills/Show', [
            'bill' => [
                'id' => $bill->id,
                'number' => $bill->number,
                'vendor_invoice_number' => $bill->vendor_invoice_number,
                'supplier' => ['id' => $bill->supplier?->id, 'name' => $bill->supplier?->name],
                'purchase_order' => $bill->purchaseOrder ? ['id' => $bill->purchaseOrder->id, 'number' => $bill->purchaseOrder->number] : null,
                'payment_status' => $bill->payment_status->value,
                'payment_status_label' => $bill->payment_status->label(),
                'payment_status_color' => $bill->payment_status->color(),
                'bill_date' => $bill->bill_date?->toDateString(),
                'due_date' => $bill->due_date?->toDateString(),
                'currency' => $bill->currency,
                'subtotal' => (float) $bill->subtotal,
                'tax_amount' => (float) $bill->tax_amount,
                'shipping_amount' => (float) $bill->shipping_amount,
                'discount_total' => (float) $bill->discount_total,
                'total' => (float) $bill->total,
                'amount_paid' => (float) $bill->amount_paid,
                'balance_due' => $bill->balanceDue(),
                'recurring' => (bool) $bill->recurring,
                'recurring_frequency' => $bill->recurring_frequency,
                'recurring_cycles' => $bill->recurring_cycles,
                'recurring_total_cycles' => $bill->recurring_total_cycles,
                'next_recurring_date' => $bill->next_recurring_date?->toDateString(),
                'notes' => $bill->notes,
                'terms' => $bill->terms,
                'items' => $bill->items->map(fn (BillItem $i) => [
                    'id' => $i->id, 'description' => $i->description, 'sku' => $i->sku, 'unit' => $i->unit,
                    'quantity' => (float) $i->quantity, 'unit_cost' => (float) $i->unit_cost,
                    'tax_rate' => (float) $i->tax_rate, 'line_total' => (float) $i->line_total,
                ]),
                'payments' => $bill->payments->map(fn (BillPayment $p) => [
                    'id' => $p->id,
                    'amount' => (float) $p->amount,
                    'payment_method' => $p->payment_method,
                    'paid_on' => $p->paid_on?->toDateString(),
                    'reference' => $p->reference,
                    'note' => $p->note,
                    'approval_status' => $p->approval_status->value,
                    'approval_status_label' => $p->approval_status->label(),
                    'approval_status_color' => $p->approval_status->color(),
                    'recorded_by' => $p->recorder?->name,
                    'approved_by' => $p->approver?->name,
                    'approval' => ApprovalController::serialize($p->latestApproval(), $user),
                ]),
            ],
            'can' => [
                'manage' => $user->can('manage bills'),
                'approvePayments' => $user->can('approve bill payments'),
            ],
            'pdf_url' => route('procurement.bills.pdf', $bill),
            'attachments' => AttachmentController::serialize($bill),
        ]);
    }

    /** Stream the branded bill PDF inline. */
    public function pdf(Request $request, Bill $bill, ProcurementDocumentService $docs)
    {
        $this->authorize('view', $bill);

        return response($docs->pdf($bill), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$docs->filename($bill).'"',
        ]);
    }

    public function update(BillRequest $request, Bill $bill): RedirectResponse
    {
        $this->authorize('update', $bill);

        $data = $request->validated();
        DB::transaction(function () use ($bill, $data) {
            $bill->update([
                'procurement_supplier_id' => $data['procurement_supplier_id'],
                'procurement_purchase_order_id' => $data['procurement_purchase_order_id'] ?? null,
                'vendor_invoice_number' => $data['vendor_invoice_number'] ?? null,
                'bill_date' => $data['bill_date'] ?? $bill->bill_date,
                'due_date' => $data['due_date'] ?? null,
                'currency' => $data['currency'],
                'shipping_amount' => $data['shipping_amount'] ?? 0,
                'discount_total' => $data['discount_total'] ?? 0,
                'recurring' => $data['recurring'] ?? false,
                'recurring_frequency' => ($data['recurring'] ?? false) ? ($data['recurring_frequency'] ?? null) : null,
                'recurring_total_cycles' => $data['recurring_total_cycles'] ?? 0,
                'next_recurring_date' => ($data['recurring'] ?? false) ? ($data['next_recurring_date'] ?? null) : null,
                'notes' => $data['notes'] ?? null,
                'terms' => $data['terms'] ?? null,
            ]);

            $bill->items()->delete();
            $this->syncItems($bill, $data['items']);
            $this->service->recalcTotals($bill);
        });

        return back()->with('success', 'Bill updated.');
    }

    public function destroy(Request $request, Bill $bill): RedirectResponse
    {
        $this->authorize('delete', $bill);
        $number = $bill->number;
        $bill->delete();

        return redirect()->route('procurement.bills.index')->with('success', "Bill {$number} deleted.");
    }

    public function recordPayment(Request $request, Bill $bill): RedirectResponse
    {
        $this->authorize('update', $bill);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0', 'max:99999999999'],
            'payment_method' => ['nullable', 'string', 'max:60'],
            'paid_on' => ['nullable', 'date'],
            'reference' => ['nullable', 'string', 'max:120'],
            'note' => ['nullable', 'string', 'max:500'],
            'require_approval' => ['nullable', 'boolean'],
        ]);

        // A recorder without the approve permission can only submit a payment for
        // approval; otherwise they may opt to require approval explicitly.
        $canApprove = $request->user()->can('approve bill payments');
        $requireApproval = ! $canApprove || (bool) ($data['require_approval'] ?? false);

        $payment = $this->service->recordPayment($bill, $data, $request->user()->id, $requireApproval);

        // If the payment needs approval and a multi-level chain is configured for
        // bill payments, instantiate it; otherwise the simple approve flow governs.
        if ($requireApproval) {
            app(ApprovalService::class)->start($payment, $request->user()->id);
        }

        return back()->with('success', $requireApproval ? 'Payment recorded — pending approval.' : 'Payment recorded.');
    }

    public function approvePayment(Request $request, Bill $bill, BillPayment $payment): RedirectResponse
    {
        $this->authorize('approvePayment', $bill);
        abort_unless($payment->procurement_bill_id === $bill->id, 404);
        if ($payment->latestApproval()?->status === ApprovalStatus::Pending) {
            return back()->with('error', 'This payment is in a multi-level approval chain — use the approval panel.');
        }

        $this->service->approvePayment($payment, $request->user()->id);

        return back()->with('success', 'Payment approved.');
    }

    public function rejectPayment(Request $request, Bill $bill, BillPayment $payment): RedirectResponse
    {
        $this->authorize('approvePayment', $bill);
        abort_unless($payment->procurement_bill_id === $bill->id, 404);
        if ($payment->latestApproval()?->status === ApprovalStatus::Pending) {
            return back()->with('error', 'This payment is in a multi-level approval chain — use the approval panel.');
        }

        $this->service->rejectPayment($payment, $request->user()->id);

        return back()->with('success', 'Payment rejected.');
    }

    private function syncItems(Bill $bill, array $items): void
    {
        foreach (array_values($items) as $position => $line) {
            $bill->items()->create([
                'organization_id' => $bill->organization_id,
                'inventory_product_id' => $line['inventory_product_id'] ?? null,
                'description' => $line['description'],
                'sku' => $line['sku'] ?? null,
                'unit' => $line['unit'] ?? null,
                'quantity' => $line['quantity'],
                'unit_cost' => $line['unit_cost'],
                'tax_rate' => $line['tax_rate'] ?? 0,
                'line_total' => round((float) $line['quantity'] * (float) $line['unit_cost'], 2),
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
                ->orderBy('name')->get(['id', 'name', 'currency']),
            'purchaseOrders' => PurchaseOrder::where('organization_id', $orgId)
                ->with('supplier:id,name')->orderByDesc('id')->limit(200)->get()
                ->map(fn (PurchaseOrder $po) => ['id' => $po->id, 'number' => $po->number, 'supplier' => $po->supplier?->name, 'supplier_id' => $po->procurement_supplier_id]),
            'products' => Product::where('organization_id', $orgId)->where('is_active', true)
                ->orderBy('name')->get(['id', 'sku', 'name', 'unit_cost']),
        ];
    }
}
