<?php

namespace App\Modules\Procurement\Services;

use App\Models\User;
use App\Modules\Procurement\Enums\PurchaseOrderStatus;
use App\Modules\Procurement\Enums\PurchaseRequestStatus;
use App\Modules\Procurement\Enums\QuotationStatus;
use App\Modules\Procurement\Models\PurchaseOrder;
use App\Modules\Procurement\Models\PurchaseRequest;
use App\Modules\Procurement\Models\Quotation;
use App\Notifications\ActivityNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use RuntimeException;

/**
 * Owns the purchase-request lifecycle (draft → pending → approved/rejected) and
 * the conversions an approved request feeds: raise vendor quotations from it, or
 * turn it straight into a purchase order.
 */
class PurchaseRequestService
{
    use ComputesDocumentTotals;

    public function __construct(
        private readonly ProcurementNumberService $numbers,
        private readonly PurchaseOrderService $purchaseOrders,
        private readonly QuotationService $quotations,
    ) {}

    /** Recompute line totals → subtotal → tax → total and persist. */
    public function recalcTotals(PurchaseRequest $pr): PurchaseRequest
    {
        $roll = $this->rollupLines($pr->items()->get());
        $pr->forceFill([
            'subtotal' => $roll['subtotal'],
            'tax_amount' => $roll['tax'],
            'total' => round($roll['subtotal'] + $roll['tax'], 2),
        ])->save();

        return $pr;
    }

    public function submit(PurchaseRequest $pr): PurchaseRequest
    {
        $pr->forceFill(['status' => PurchaseRequestStatus::PendingApproval])->save();
        $this->notifyApprovers($pr);

        return $pr;
    }

    public function approve(PurchaseRequest $pr, int $approvedBy): PurchaseRequest
    {
        $pr->forceFill([
            'status' => PurchaseRequestStatus::Approved,
            'approved_by' => $approvedBy,
            'approved_at' => now(),
            'rejected_reason' => null,
        ])->save();

        return $pr;
    }

    public function reject(PurchaseRequest $pr, ?string $reason, int $rejectedBy): PurchaseRequest
    {
        $pr->forceFill([
            'status' => PurchaseRequestStatus::Rejected,
            'approved_by' => $rejectedBy,
            'approved_at' => now(),
            'rejected_reason' => $reason,
        ])->save();

        return $pr;
    }

    public function cancel(PurchaseRequest $pr): PurchaseRequest
    {
        $pr->forceFill(['status' => PurchaseRequestStatus::Cancelled])->save();

        return $pr;
    }

    /**
     * Raise a vendor quotation (RFQ) from an approved request — copies its lines
     * so the vendor can price them. The request stays approved (you can request
     * quotes from several vendors before choosing one).
     */
    public function convertToQuotation(PurchaseRequest $pr, int $supplierId, int $actorId): Quotation
    {
        if (! $pr->status->canConvert()) {
            throw new RuntimeException('Only an approved purchase request can be sent for quotation.');
        }

        return DB::transaction(function () use ($pr, $supplierId, $actorId) {
            $quotation = Quotation::create([
                'organization_id' => $pr->organization_id,
                'created_by' => $actorId,
                'procurement_purchase_request_id' => $pr->id,
                'procurement_supplier_id' => $supplierId,
                'number' => $this->numbers->quotation($pr->organization_id),
                'status' => QuotationStatus::Draft,
                'quote_date' => now()->toDateString(),
                'currency' => $pr->currency,
            ]);

            foreach ($pr->items()->orderBy('position')->orderBy('id')->get() as $item) {
                $quotation->items()->create([
                    'organization_id' => $pr->organization_id,
                    'inventory_product_id' => $item->inventory_product_id,
                    'description' => $item->description,
                    'sku' => $item->sku,
                    'unit' => $item->unit,
                    'quantity' => $item->quantity,
                    'unit_cost' => $item->unit_cost,
                    'tax_rate' => $item->tax_rate,
                    'line_total' => round((float) $item->quantity * (float) $item->unit_cost, 2),
                    'position' => $item->position,
                ]);
            }

            $this->quotations->recalcTotals($quotation);

            return $quotation->fresh();
        });
    }

    /**
     * Turn an approved request straight into a draft purchase order for a vendor,
     * copying its lines. Marks the request Converted.
     */
    /**
     * Raise a draft purchase request from a CRM sales invoice or estimate,
     * copying its line items (sale price becomes the requested unit cost). The
     * source document is read-only — nothing on the CRM side changes.
     */
    public function fromCrmInvoice(\App\Models\Crm\Invoice $invoice, int $actorId): PurchaseRequest
    {
        return DB::transaction(function () use ($invoice, $actorId) {
            $pr = PurchaseRequest::create([
                'organization_id' => $invoice->organization_id,
                'created_by' => $actorId,
                'requester_id' => $actorId,
                'crm_project_id' => $invoice->crm_project_id,
                'number' => $this->numbers->purchaseRequest($invoice->organization_id),
                'title' => 'From '.($invoice->isEstimate() ? 'estimate' : 'invoice').' '.$invoice->number,
                'status' => PurchaseRequestStatus::Draft,
                'currency' => $invoice->currency ?: 'USD',
            ]);

            foreach ($invoice->items()->get() as $i) {
                $pr->items()->create([
                    'organization_id' => $invoice->organization_id,
                    'description' => $i->description,
                    'quantity' => $i->quantity,
                    'unit_cost' => $i->unit_price,
                    'tax_rate' => 0,
                    'line_total' => round((float) $i->quantity * (float) $i->unit_price, 2),
                    'position' => $i->position,
                ]);
            }

            $this->recalcTotals($pr);

            return $pr->fresh();
        });
    }

    public function convertToPurchaseOrder(PurchaseRequest $pr, int $supplierId, int $actorId): PurchaseOrder
    {
        if (! $pr->status->canConvert()) {
            throw new RuntimeException('Only an approved purchase request can be converted to a purchase order.');
        }

        return DB::transaction(function () use ($pr, $supplierId, $actorId) {
            $po = PurchaseOrder::create([
                'organization_id' => $pr->organization_id,
                'created_by' => $actorId,
                'procurement_supplier_id' => $supplierId,
                'procurement_purchase_request_id' => $pr->id,
                'crm_project_id' => $pr->crm_project_id,
                'number' => $this->numbers->generate($pr->organization_id),
                'status' => PurchaseOrderStatus::Draft,
                'order_date' => now()->toDateString(),
                'currency' => $pr->currency,
                'tax_rate' => 0,
                'shipping_amount' => 0,
            ]);

            foreach ($pr->items()->orderBy('position')->orderBy('id')->get() as $item) {
                $po->items()->create([
                    'organization_id' => $pr->organization_id,
                    'inventory_product_id' => $item->inventory_product_id,
                    'description' => $item->description,
                    'sku' => $item->sku,
                    'quantity_ordered' => $item->quantity,
                    'unit_cost' => $item->unit_cost,
                    'line_total' => round((float) $item->quantity * (float) $item->unit_cost, 2),
                    'position' => $item->position,
                ]);
            }

            $this->purchaseOrders->recalcTotals($po);
            $pr->forceFill(['status' => PurchaseRequestStatus::Converted])->save();

            return $po->fresh();
        });
    }

    /** Alert everyone who can approve purchase requests that one is waiting. Best-effort. */
    private function notifyApprovers(PurchaseRequest $pr): void
    {
        $approvers = User::query()
            ->where('organization_id', $pr->organization_id)
            ->permission('approve purchase requests')
            ->get();

        if ($approvers->isEmpty()) {
            return;
        }

        try {
            Notification::send($approvers, new ActivityNotification([
                'type' => 'procurement',
                'title' => "Approval needed: purchase request {$pr->number}",
                'message' => $pr->title.' — submitted for approval.',
                'url' => route('procurement.purchase-requests.show', $pr),
                'icon' => 'clipboard-list',
                'email' => true,
            ]));
        } catch (\Throwable $e) {
            Log::warning('Purchase request approver notification failed', ['pr' => $pr->number, 'error' => $e->getMessage()]);
        }
    }
}
