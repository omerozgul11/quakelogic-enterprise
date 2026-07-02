<?php

namespace App\Modules\Procurement\Services;

use App\Modules\Procurement\Enums\PurchaseOrderStatus;
use App\Modules\Procurement\Enums\PurchaseRequestStatus;
use App\Modules\Procurement\Enums\QuotationStatus;
use App\Modules\Procurement\Models\PurchaseOrder;
use App\Modules\Procurement\Models\Quotation;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Owns the quotation (RFQ) lifecycle: send a quote request to a vendor, record
 * that it came back priced, and accept the chosen one — which raises a purchase
 * order from its lines and marks any originating request Converted.
 */
class QuotationService
{
    use ComputesDocumentTotals;

    public function __construct(
        private readonly ProcurementNumberService $numbers,
        private readonly PurchaseOrderService $purchaseOrders,
    ) {}

    public function recalcTotals(Quotation $quotation): Quotation
    {
        $roll = $this->rollupLines($quotation->items()->get());
        $subtotal = $roll['subtotal'];
        $quotation->forceFill([
            'subtotal' => $subtotal,
            'tax_amount' => $roll['tax'],
            'total' => round($subtotal + $roll['tax'] - (float) $quotation->discount_total, 2),
        ])->save();

        return $quotation;
    }

    public function send(Quotation $quotation): Quotation
    {
        $quotation->forceFill(['status' => QuotationStatus::Sent, 'sent_at' => now()])->save();

        return $quotation;
    }

    public function markReceived(Quotation $quotation): Quotation
    {
        $quotation->forceFill(['status' => QuotationStatus::Received])->save();

        return $quotation;
    }

    public function reject(Quotation $quotation): Quotation
    {
        $quotation->forceFill(['status' => QuotationStatus::Rejected])->save();

        return $quotation;
    }

    /**
     * Accept the quote and raise a draft purchase order from its lines, linking
     * both the quotation and its originating request; the request is marked
     * Converted.
     */
    public function accept(Quotation $quotation, int $actorId): PurchaseOrder
    {
        if (! $quotation->status->canAccept()) {
            throw new RuntimeException('Only a sent or received quotation can be accepted.');
        }

        return DB::transaction(function () use ($quotation, $actorId) {
            $quotation->forceFill(['status' => QuotationStatus::Accepted, 'accepted_at' => now()])->save();

            $po = PurchaseOrder::create([
                'organization_id' => $quotation->organization_id,
                'created_by' => $actorId,
                'procurement_supplier_id' => $quotation->procurement_supplier_id,
                'procurement_quotation_id' => $quotation->id,
                'procurement_purchase_request_id' => $quotation->procurement_purchase_request_id,
                'number' => $this->numbers->generate($quotation->organization_id),
                'status' => PurchaseOrderStatus::Draft,
                'order_date' => now()->toDateString(),
                'currency' => $quotation->currency,
                'tax_rate' => 0,
                'shipping_amount' => 0,
            ]);

            foreach ($quotation->items()->orderBy('position')->orderBy('id')->get() as $item) {
                $po->items()->create([
                    'organization_id' => $quotation->organization_id,
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

            // Close out the originating request, if any.
            $pr = $quotation->purchaseRequest()->first();
            if ($pr && $pr->status !== PurchaseRequestStatus::Converted) {
                $pr->forceFill(['status' => PurchaseRequestStatus::Converted])->save();
            }

            return $po->fresh();
        });
    }
}
