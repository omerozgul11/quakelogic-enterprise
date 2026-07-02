<?php

namespace App\Modules\Procurement\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Models\Product;
use App\Modules\Procurement\Enums\PurchaseRequestStatus;
use App\Modules\Procurement\Enums\QuotationStatus;
use App\Modules\Procurement\Http\Requests\QuotationRequest;
use App\Modules\Procurement\Http\Requests\SendDocumentRequest;
use App\Modules\Procurement\Models\PurchaseRequest;
use App\Modules\Procurement\Models\Quotation;
use App\Modules\Procurement\Models\QuotationItem;
use App\Modules\Procurement\Models\Supplier;
use App\Modules\Procurement\Services\ProcurementDocumentService;
use App\Modules\Procurement\Services\ProcurementNumberService;
use App\Modules\Procurement\Services\QuotationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class QuotationController extends Controller
{
    public function __construct(
        private readonly QuotationService $service,
        private readonly ProcurementNumberService $numbers,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Quotation::class);
        $orgId = $request->user()->organization_id;

        $quotations = Quotation::where('organization_id', $orgId)
            ->with('supplier:id,name')
            ->when($request->search, fn ($q, $s) => $q->where(fn ($w) => $w
                ->where('number', 'like', "%{$s}%")
                ->orWhereHas('supplier', fn ($sup) => $sup->where('name', 'like', "%{$s}%"))))
            ->when($request->status, fn ($q, $st) => $q->where('status', $st))
            ->latest('id')
            ->paginate(20)->withQueryString()
            ->through(fn (Quotation $q) => [
                'id' => $q->id,
                'number' => $q->number,
                'supplier' => $q->supplier?->name,
                'status' => $q->status->value,
                'status_label' => $q->status->label(),
                'status_color' => $q->status->color(),
                'total' => (float) $q->total,
                'currency' => $q->currency,
                'quote_date' => $q->quote_date?->toDateString(),
            ]);

        return Inertia::render('Procurement/Quotations/Index', [
            'quotations' => $quotations,
            'filters' => $request->only(['search', 'status']),
            'statuses' => QuotationStatus::options(),
            'can' => ['manage' => $request->user()->can('manage quotations')],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Quotation::class);

        return Inertia::render('Procurement/Quotations/Create', $this->formData($request));
    }

    public function store(QuotationRequest $request): RedirectResponse
    {
        $this->authorize('create', Quotation::class);
        $user = $request->user();
        $data = $request->validated();

        $quotation = DB::transaction(function () use ($data, $user) {
            $quotation = Quotation::create([
                'organization_id' => $user->organization_id,
                'created_by' => $user->id,
                'procurement_supplier_id' => $data['procurement_supplier_id'],
                'procurement_purchase_request_id' => $data['procurement_purchase_request_id'] ?? null,
                'number' => $this->numbers->quotation($user->organization_id),
                'reference_no' => $data['reference_no'] ?? null,
                'status' => QuotationStatus::Draft,
                'quote_date' => $data['quote_date'] ?? now()->toDateString(),
                'expiry_date' => $data['expiry_date'] ?? null,
                'currency' => $data['currency'],
                'discount_total' => $data['discount_total'] ?? 0,
                'vendor_note' => $data['vendor_note'] ?? null,
                'admin_note' => $data['admin_note'] ?? null,
                'terms' => $data['terms'] ?? null,
            ]);

            $this->syncItems($quotation, $data['items']);
            $this->service->recalcTotals($quotation);

            return $quotation;
        });

        return redirect()->route('procurement.quotations.show', $quotation)->with('success', "Quotation {$quotation->number} created.");
    }

    public function show(Request $request, Quotation $quotation, ProcurementDocumentService $docs): Response
    {
        $this->authorize('view', $quotation);

        $quotation->load([
            'supplier:id,name,email', 'purchaseRequest:id,number',
            'items' => fn ($q) => $q->orderBy('position')->orderBy('id'),
            'purchaseOrders:id,procurement_quotation_id,number,status,total,currency',
            'attachments.uploader:id,name',
        ]);

        $user = $request->user();

        return Inertia::render('Procurement/Quotations/Show', [
            'quotation' => [
                'id' => $quotation->id,
                'number' => $quotation->number,
                'reference_no' => $quotation->reference_no,
                'supplier' => ['id' => $quotation->supplier?->id, 'name' => $quotation->supplier?->name],
                'purchase_request' => $quotation->purchaseRequest ? ['id' => $quotation->purchaseRequest->id, 'number' => $quotation->purchaseRequest->number] : null,
                'status' => $quotation->status->value,
                'status_label' => $quotation->status->label(),
                'status_color' => $quotation->status->color(),
                'is_editable' => $quotation->status->isEditable(),
                'can_accept' => $quotation->status->canAccept(),
                'quote_date' => $quotation->quote_date?->toDateString(),
                'expiry_date' => $quotation->expiry_date?->toDateString(),
                'currency' => $quotation->currency,
                'subtotal' => (float) $quotation->subtotal,
                'tax_amount' => (float) $quotation->tax_amount,
                'discount_total' => (float) $quotation->discount_total,
                'total' => (float) $quotation->total,
                'vendor_note' => $quotation->vendor_note,
                'admin_note' => $quotation->admin_note,
                'terms' => $quotation->terms,
                'items' => $quotation->items->map(fn (QuotationItem $i) => [
                    'id' => $i->id, 'description' => $i->description, 'sku' => $i->sku, 'unit' => $i->unit,
                    'quantity' => (float) $i->quantity, 'unit_cost' => (float) $i->unit_cost,
                    'tax_rate' => (float) $i->tax_rate, 'line_total' => (float) $i->line_total,
                ]),
                'purchase_orders' => $quotation->purchaseOrders->map(fn ($po) => [
                    'id' => $po->id, 'number' => $po->number,
                    'status' => $po->status->value, 'status_label' => $po->status->label(), 'status_color' => $po->status->color(),
                    'total' => (float) $po->total, 'currency' => $po->currency,
                ]),
            ],
            'can' => [
                'manage' => $user->can('manage quotations'),
                'createOrder' => $user->can('manage purchase orders'),
            ],
            'send' => array_merge($docs->sendMeta($quotation), [
                'pdf_url' => route('procurement.quotations.pdf', $quotation),
                'send_url' => route('procurement.quotations.send-email', $quotation),
                'sent_at' => $quotation->sent_at?->toIso8601String(),
            ]),
            'attachments' => AttachmentController::serialize($quotation),
        ]);
    }

    /** Stream the branded RFQ PDF inline. */
    public function pdf(Request $request, Quotation $quotation, ProcurementDocumentService $docs)
    {
        $this->authorize('view', $quotation);

        return response($docs->pdf($quotation), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$docs->filename($quotation).'"',
        ]);
    }

    /** Email the RFQ (PDF + covering message) to the vendor and mark it sent. */
    public function sendEmail(SendDocumentRequest $request, Quotation $quotation, ProcurementDocumentService $docs): RedirectResponse
    {
        $this->authorize('update', $quotation);

        try {
            $docs->sendEmail($quotation, $request->validated());
        } catch (\Throwable $e) {
            Log::warning('Quotation send-email failed', ['quotation' => $quotation->number, 'error' => $e->getMessage()]);

            return back()->with('error', 'Could not send the email. '.$e->getMessage());
        }

        $this->service->send($quotation);

        return back()->with('success', "Quotation {$quotation->number} emailed to {$request->validated()['to']}.");
    }

    public function update(QuotationRequest $request, Quotation $quotation): RedirectResponse
    {
        $this->authorize('update', $quotation);

        if (! $quotation->status->isEditable()) {
            return back()->with('error', 'This quotation can no longer be edited.');
        }

        $data = $request->validated();
        DB::transaction(function () use ($quotation, $data) {
            $quotation->update([
                'procurement_supplier_id' => $data['procurement_supplier_id'],
                'procurement_purchase_request_id' => $data['procurement_purchase_request_id'] ?? null,
                'reference_no' => $data['reference_no'] ?? null,
                'quote_date' => $data['quote_date'] ?? $quotation->quote_date,
                'expiry_date' => $data['expiry_date'] ?? null,
                'currency' => $data['currency'],
                'discount_total' => $data['discount_total'] ?? 0,
                'vendor_note' => $data['vendor_note'] ?? null,
                'admin_note' => $data['admin_note'] ?? null,
                'terms' => $data['terms'] ?? null,
            ]);

            $quotation->items()->delete();
            $this->syncItems($quotation, $data['items']);
            $this->service->recalcTotals($quotation);
        });

        return back()->with('success', 'Quotation updated.');
    }

    public function destroy(Request $request, Quotation $quotation): RedirectResponse
    {
        $this->authorize('delete', $quotation);
        $number = $quotation->number;
        $quotation->delete();

        return redirect()->route('procurement.quotations.index')->with('success', "Quotation {$number} deleted.");
    }

    public function send(Request $request, Quotation $quotation): RedirectResponse
    {
        $this->authorize('update', $quotation);
        $this->service->send($quotation);

        return back()->with('success', 'Quotation marked as sent.');
    }

    public function markReceived(Request $request, Quotation $quotation): RedirectResponse
    {
        $this->authorize('update', $quotation);
        $this->service->markReceived($quotation);

        return back()->with('success', 'Quotation marked as received.');
    }

    public function reject(Request $request, Quotation $quotation): RedirectResponse
    {
        $this->authorize('update', $quotation);
        $this->service->reject($quotation);

        return back()->with('success', 'Quotation rejected.');
    }

    public function accept(Request $request, Quotation $quotation): RedirectResponse
    {
        $this->authorize('update', $quotation);
        abort_unless($request->user()->can('manage purchase orders'), 403);

        try {
            $po = $this->service->accept($quotation, $request->user()->id);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('procurement.purchase-orders.show', $po)
            ->with('success', "Quotation accepted — purchase order {$po->number} created.");
    }

    private function syncItems(Quotation $quotation, array $items): void
    {
        foreach (array_values($items) as $position => $line) {
            $quotation->items()->create([
                'organization_id' => $quotation->organization_id,
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
            'purchaseRequests' => PurchaseRequest::where('organization_id', $orgId)
                ->whereIn('status', [PurchaseRequestStatus::Approved->value, PurchaseRequestStatus::Converted->value])
                ->orderByDesc('id')->get(['id', 'number', 'title']),
            'products' => Product::where('organization_id', $orgId)->where('is_active', true)
                ->orderBy('name')->get(['id', 'sku', 'name', 'unit_cost']),
        ];
    }
}
