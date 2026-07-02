<?php

namespace App\Modules\Procurement\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Crm\Project;
use App\Models\User;
use App\Modules\Inventory\Models\Product;
use App\Modules\Procurement\Enums\ApprovalStatus;
use App\Modules\Procurement\Enums\PurchaseRequestStatus;
use App\Modules\Procurement\Services\ApprovalService;
use App\Modules\Procurement\Http\Requests\PurchaseRequestRequest;
use App\Modules\Procurement\Http\Requests\SendDocumentRequest;
use App\Modules\Procurement\Models\PurchaseRequest;
use App\Modules\Procurement\Models\PurchaseRequestItem;
use App\Modules\Procurement\Models\Supplier;
use App\Modules\Procurement\Services\ProcurementDocumentService;
use App\Modules\Procurement\Services\ProcurementNumberService;
use App\Modules\Procurement\Services\PurchaseRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class PurchaseRequestController extends Controller
{
    public function __construct(
        private readonly PurchaseRequestService $service,
        private readonly ProcurementNumberService $numbers,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', PurchaseRequest::class);
        $orgId = $request->user()->organization_id;

        $requests = PurchaseRequest::where('organization_id', $orgId)
            ->with('requester:id,name')
            ->when($request->search, fn ($q, $s) => $q->where(fn ($w) => $w
                ->where('number', 'like', "%{$s}%")->orWhere('title', 'like', "%{$s}%")))
            ->when($request->status, fn ($q, $st) => $q->where('status', $st))
            ->latest('id')
            ->paginate(20)->withQueryString()
            ->through(fn (PurchaseRequest $pr) => [
                'id' => $pr->id,
                'number' => $pr->number,
                'title' => $pr->title,
                'requester' => $pr->requester?->name,
                'department' => $pr->department,
                'status' => $pr->status->value,
                'status_label' => $pr->status->label(),
                'status_color' => $pr->status->color(),
                'total' => (float) $pr->total,
                'currency' => $pr->currency,
                'created_at' => $pr->created_at?->toDateString(),
            ]);

        return Inertia::render('Procurement/PurchaseRequests/Index', [
            'requests' => $requests,
            'filters' => $request->only(['search', 'status']),
            'statuses' => PurchaseRequestStatus::options(),
            'can' => ['manage' => $request->user()->can('manage purchase requests')],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', PurchaseRequest::class);

        return Inertia::render('Procurement/PurchaseRequests/Create', $this->formData($request));
    }

    public function store(PurchaseRequestRequest $request): RedirectResponse
    {
        $this->authorize('create', PurchaseRequest::class);
        $user = $request->user();
        $data = $request->validated();

        $pr = DB::transaction(function () use ($data, $user) {
            $pr = PurchaseRequest::create([
                'organization_id' => $user->organization_id,
                'created_by' => $user->id,
                'requester_id' => $data['requester_id'] ?? $user->id,
                'crm_project_id' => $data['crm_project_id'] ?? null,
                'number' => $this->numbers->purchaseRequest($user->organization_id),
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'department' => $data['department'] ?? null,
                'status' => PurchaseRequestStatus::Draft,
                'currency' => $data['currency'],
                'notes' => $data['notes'] ?? null,
            ]);

            $this->syncItems($pr, $data['items']);
            $this->service->recalcTotals($pr);

            return $pr;
        });

        return redirect()->route('procurement.purchase-requests.show', $pr)->with('success', "Purchase request {$pr->number} created.");
    }

    public function show(Request $request, PurchaseRequest $purchaseRequest, ProcurementDocumentService $docs): Response
    {
        $this->authorize('view', $purchaseRequest);

        $purchaseRequest->load([
            'requester:id,name', 'approver:id,name', 'project:id,name',
            'items' => fn ($q) => $q->orderBy('position')->orderBy('id'),
            'quotations:id,procurement_purchase_request_id,procurement_supplier_id,number,status,total,currency',
            'quotations.supplier:id,name',
            'purchaseOrders:id,procurement_purchase_request_id,number,status,total,currency',
            'attachments.uploader:id,name',
        ]);

        $user = $request->user();

        return Inertia::render('Procurement/PurchaseRequests/Show', [
            'request' => [
                'id' => $purchaseRequest->id,
                'number' => $purchaseRequest->number,
                'title' => $purchaseRequest->title,
                'description' => $purchaseRequest->description,
                'department' => $purchaseRequest->department,
                'requester' => $purchaseRequest->requester?->name,
                'project' => $purchaseRequest->project?->name,
                'status' => $purchaseRequest->status->value,
                'status_label' => $purchaseRequest->status->label(),
                'status_color' => $purchaseRequest->status->color(),
                'is_editable' => $purchaseRequest->status->isEditable(),
                'can_convert' => $purchaseRequest->status->canConvert(),
                'currency' => $purchaseRequest->currency,
                'subtotal' => (float) $purchaseRequest->subtotal,
                'tax_amount' => (float) $purchaseRequest->tax_amount,
                'total' => (float) $purchaseRequest->total,
                'notes' => $purchaseRequest->notes,
                'rejected_reason' => $purchaseRequest->rejected_reason,
                'approved_by' => $purchaseRequest->approver?->name,
                'approved_at' => $purchaseRequest->approved_at?->toIso8601String(),
                'items' => $purchaseRequest->items->map(fn (PurchaseRequestItem $i) => [
                    'id' => $i->id,
                    'description' => $i->description,
                    'sku' => $i->sku,
                    'unit' => $i->unit,
                    'quantity' => (float) $i->quantity,
                    'unit_cost' => (float) $i->unit_cost,
                    'tax_rate' => (float) $i->tax_rate,
                    'line_total' => (float) $i->line_total,
                ]),
                'quotations' => $purchaseRequest->quotations->map(fn ($q) => [
                    'id' => $q->id, 'number' => $q->number, 'supplier' => $q->supplier?->name,
                    'status' => $q->status->value, 'status_label' => $q->status->label(), 'status_color' => $q->status->color(),
                    'total' => (float) $q->total, 'currency' => $q->currency,
                ]),
                'purchase_orders' => $purchaseRequest->purchaseOrders->map(fn ($po) => [
                    'id' => $po->id, 'number' => $po->number,
                    'status' => $po->status->value, 'status_label' => $po->status->label(), 'status_color' => $po->status->color(),
                    'total' => (float) $po->total, 'currency' => $po->currency,
                ]),
            ],
            'suppliers' => Supplier::where('organization_id', $user->organization_id)->where('status', 'active')
                ->orderBy('name')->get(['id', 'name']),
            'can' => [
                'manage' => $user->can('manage purchase requests'),
                'approve' => $user->can('approve purchase requests'),
                'createQuotation' => $user->can('manage quotations'),
                'createOrder' => $user->can('manage purchase orders'),
            ],
            'send' => array_merge($docs->sendMeta($purchaseRequest), [
                'pdf_url' => route('procurement.purchase-requests.pdf', $purchaseRequest),
                'send_url' => route('procurement.purchase-requests.send-email', $purchaseRequest),
                'sent_at' => null,
            ]),
            'attachments' => AttachmentController::serialize($purchaseRequest),
            'approval' => ApprovalController::serialize($purchaseRequest->latestApproval(), $user),
        ]);
    }

    /** Stream the branded PR PDF inline. */
    public function pdf(Request $request, PurchaseRequest $purchaseRequest, ProcurementDocumentService $docs)
    {
        $this->authorize('view', $purchaseRequest);

        return response($docs->pdf($purchaseRequest), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$docs->filename($purchaseRequest).'"',
        ]);
    }

    /** Email the PR (PDF + covering message) to a chosen recipient. */
    public function sendEmail(SendDocumentRequest $request, PurchaseRequest $purchaseRequest, ProcurementDocumentService $docs): RedirectResponse
    {
        $this->authorize('update', $purchaseRequest);

        try {
            $docs->sendEmail($purchaseRequest, $request->validated());
        } catch (\Throwable $e) {
            Log::warning('Purchase request send-email failed', ['pr' => $purchaseRequest->number, 'error' => $e->getMessage()]);

            return back()->with('error', 'Could not send the email. '.$e->getMessage());
        }

        return back()->with('success', "Purchase request {$purchaseRequest->number} emailed to {$request->validated()['to']}.");
    }

    public function update(PurchaseRequestRequest $request, PurchaseRequest $purchaseRequest): RedirectResponse
    {
        $this->authorize('update', $purchaseRequest);

        if (! $purchaseRequest->status->isEditable()) {
            return back()->with('error', 'Only a draft purchase request can be edited.');
        }

        $data = $request->validated();
        DB::transaction(function () use ($purchaseRequest, $data) {
            $purchaseRequest->update([
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'department' => $data['department'] ?? null,
                'requester_id' => $data['requester_id'] ?? $purchaseRequest->requester_id,
                'crm_project_id' => $data['crm_project_id'] ?? null,
                'currency' => $data['currency'],
                'notes' => $data['notes'] ?? null,
            ]);

            $purchaseRequest->items()->delete();
            $this->syncItems($purchaseRequest, $data['items']);
            $this->service->recalcTotals($purchaseRequest);
        });

        return back()->with('success', 'Purchase request updated.');
    }

    public function destroy(Request $request, PurchaseRequest $purchaseRequest): RedirectResponse
    {
        $this->authorize('delete', $purchaseRequest);
        $number = $purchaseRequest->number;
        $purchaseRequest->delete();

        return redirect()->route('procurement.purchase-requests.index')->with('success', "Purchase request {$number} deleted.");
    }

    public function submit(Request $request, PurchaseRequest $purchaseRequest, ApprovalService $approvals): RedirectResponse
    {
        $this->authorize('update', $purchaseRequest);
        $this->service->submit($purchaseRequest);
        // Instantiate a multi-level chain if one is configured; otherwise the
        // simple approve/reject flow governs.
        $approvals->start($purchaseRequest, $request->user()->id);

        return back()->with('success', 'Submitted for approval.');
    }

    public function approve(Request $request, PurchaseRequest $purchaseRequest): RedirectResponse
    {
        $this->authorize('approve', $purchaseRequest);
        if ($purchaseRequest->latestApproval()?->status === ApprovalStatus::Pending) {
            return back()->with('error', 'This request is in a multi-level approval chain — use the approval panel.');
        }
        $this->service->approve($purchaseRequest, $request->user()->id);

        return back()->with('success', "Purchase request {$purchaseRequest->number} approved.");
    }

    public function reject(Request $request, PurchaseRequest $purchaseRequest): RedirectResponse
    {
        $this->authorize('approve', $purchaseRequest);
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);
        $this->service->reject($purchaseRequest, $data['reason'] ?? null, $request->user()->id);

        return back()->with('success', "Purchase request {$purchaseRequest->number} rejected.");
    }

    public function cancel(Request $request, PurchaseRequest $purchaseRequest): RedirectResponse
    {
        $this->authorize('update', $purchaseRequest);
        $this->service->cancel($purchaseRequest);

        return back()->with('success', 'Purchase request cancelled.');
    }

    public function convertToQuotation(Request $request, PurchaseRequest $purchaseRequest): RedirectResponse
    {
        $this->authorize('view', $purchaseRequest);
        abort_unless($request->user()->can('manage quotations'), 403);

        $data = $this->validateSupplier($request);
        try {
            $quotation = $this->service->convertToQuotation($purchaseRequest, (int) $data['procurement_supplier_id'], $request->user()->id);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('procurement.quotations.show', $quotation)
            ->with('success', "Quotation {$quotation->number} created from {$purchaseRequest->number}.");
    }

    public function convertToOrder(Request $request, PurchaseRequest $purchaseRequest): RedirectResponse
    {
        $this->authorize('view', $purchaseRequest);
        abort_unless($request->user()->can('manage purchase orders'), 403);

        $data = $this->validateSupplier($request);
        try {
            $po = $this->service->convertToPurchaseOrder($purchaseRequest, (int) $data['procurement_supplier_id'], $request->user()->id);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('procurement.purchase-orders.show', $po)
            ->with('success', "Purchase order {$po->number} created from {$purchaseRequest->number}.");
    }

    /** @return array<string,mixed> */
    private function validateSupplier(Request $request): array
    {
        return $request->validate([
            'procurement_supplier_id' => [
                'required',
                \Illuminate\Validation\Rule::exists('procurement_suppliers', 'id')
                    ->where('organization_id', $request->user()->organization_id)->whereNull('deleted_at'),
            ],
        ]);
    }

    /** Persist PR line items from validated request data. */
    private function syncItems(PurchaseRequest $pr, array $items): void
    {
        foreach (array_values($items) as $position => $line) {
            $pr->items()->create([
                'organization_id' => $pr->organization_id,
                'inventory_product_id' => $line['inventory_product_id'] ?? null,
                'description' => $line['description'],
                'sku' => $line['sku'] ?? null,
                'unit' => $line['unit'] ?? null,
                'quantity' => $line['quantity'],
                'unit_cost' => $line['unit_cost'] ?? 0,
                'tax_rate' => $line['tax_rate'] ?? 0,
                'line_total' => round((float) $line['quantity'] * (float) ($line['unit_cost'] ?? 0), 2),
                'position' => $position,
            ]);
        }
    }

    /** @return array<string,mixed> */
    /** Raise a draft purchase request by copying a CRM sales invoice/estimate. */
    public function storeFromInvoice(Request $request, \App\Models\Crm\Invoice $invoice): RedirectResponse
    {
        $this->authorize('create', PurchaseRequest::class);
        abort_unless($invoice->organization_id === $request->user()->organization_id, 404);

        $pr = $this->service->fromCrmInvoice($invoice, $request->user()->id);

        return redirect()->route('procurement.purchase-requests.show', $pr)
            ->with('success', "Draft purchase request created from {$invoice->number}.");
    }

    private function formData(Request $request): array
    {
        $orgId = $request->user()->organization_id;

        return [
            'users' => User::where('organization_id', $orgId)->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'projects' => Project::where('organization_id', $orgId)->orderBy('name')->get(['id', 'name']),
            'products' => Product::where('organization_id', $orgId)->where('is_active', true)
                ->orderBy('name')->get(['id', 'sku', 'name', 'unit_cost']),
            'sourceInvoices' => self::sourceInvoices($orgId),
        ];
    }

    /**
     * CRM sales invoices/estimates a purchase document can be copied from.
     * Mapped to plain rows so the Invoice model's appended `balance` accessor
     * (which needs unselected columns) never fires during serialization.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function sourceInvoices(int $orgId): array
    {
        return \App\Models\Crm\Invoice::where('organization_id', $orgId)
            ->whereIn('kind', ['invoice', 'estimate'])
            ->with('company:id,name')
            ->orderByDesc('id')->limit(100)
            ->get(['id', 'number', 'kind', 'company_id', 'total', 'amount_paid', 'currency'])
            ->map(fn ($inv) => [
                'id' => $inv->id,
                'number' => $inv->number,
                'kind' => $inv->kind,
                'company' => $inv->company?->name,
                'total' => (float) $inv->total,
                'currency' => $inv->currency,
            ])->all();
    }
}
