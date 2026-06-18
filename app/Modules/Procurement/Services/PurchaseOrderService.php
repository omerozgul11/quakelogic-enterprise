<?php

namespace App\Modules\Procurement\Services;

use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Procurement\Enums\PurchaseOrderStatus;
use App\Modules\Procurement\Models\PurchaseOrder;
use App\Modules\Procurement\Models\PurchaseOrderItem;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Owns the purchase-order lifecycle and goods receipt. Receiving a line that is
 * linked to an inventory product (on a PO with a destination warehouse) posts a
 * stock receipt through InventoryService — the one integration point between
 * Procurement and Inventory.
 */
class PurchaseOrderService
{
    public function __construct(private readonly InventoryService $inventory) {}

    /** Recompute line totals → subtotal → tax → grand total and persist. */
    public function recalcTotals(PurchaseOrder $po): PurchaseOrder
    {
        $po->load('items');

        $subtotal = 0.0;
        foreach ($po->items as $item) {
            $lineTotal = round((float) $item->quantity_ordered * (float) $item->unit_cost, 2);
            if ((float) $item->line_total !== $lineTotal) {
                $item->forceFill(['line_total' => $lineTotal])->save();
            }
            $subtotal += $lineTotal;
        }

        $taxAmount = round($subtotal * (float) $po->tax_rate / 100, 2);
        $po->forceFill([
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total' => round($subtotal + $taxAmount + (float) $po->shipping_amount, 2),
        ])->save();

        return $po;
    }

    public function submit(PurchaseOrder $po): PurchaseOrder
    {
        $po->forceFill(['status' => PurchaseOrderStatus::PendingApproval])->save();

        return $po;
    }

    public function approve(PurchaseOrder $po, int $approvedBy): PurchaseOrder
    {
        $po->forceFill([
            'status' => PurchaseOrderStatus::Approved,
            'approved_by' => $approvedBy,
            'approved_at' => now(),
        ])->save();

        return $po;
    }

    public function markSent(PurchaseOrder $po): PurchaseOrder
    {
        $po->forceFill(['status' => PurchaseOrderStatus::Sent])->save();

        return $po;
    }

    public function cancel(PurchaseOrder $po): PurchaseOrder
    {
        if (in_array($po->status, [PurchaseOrderStatus::Received, PurchaseOrderStatus::Closed], true)) {
            throw new RuntimeException('A received or closed purchase order cannot be cancelled.');
        }

        $po->forceFill(['status' => PurchaseOrderStatus::Cancelled])->save();

        return $po;
    }

    /**
     * Receive a quantity against one PO line. Updates quantity_received, posts a
     * stock receipt when the line maps to an inventory product + warehouse, and
     * rolls the PO status forward. Returns the refreshed item.
     */
    public function receiveItem(PurchaseOrderItem $item, float $quantity, array $opts = []): PurchaseOrderItem
    {
        if ($quantity <= 0) {
            throw new RuntimeException('Receive quantity must be greater than zero.');
        }

        return DB::transaction(function () use ($item, $quantity, $opts) {
            $item->refresh();
            $po = $item->purchaseOrder()->lockForUpdate()->first();

            if (! $po->status->canReceive()) {
                throw new RuntimeException("Purchase order {$po->number} is not in a receivable state.");
            }

            $outstanding = $item->outstanding();
            $receiveQty = min($quantity, $outstanding);
            if ($receiveQty <= 0) {
                throw new RuntimeException('This line is already fully received.');
            }

            $item->forceFill(['quantity_received' => (float) $item->quantity_received + $receiveQty])->save();

            // Post to inventory only for stocked lines on a PO with a destination.
            // Resolve relations with explicit queries (lazy loading is disabled).
            $product = $item->inventory_product_id ? $item->product()->first() : null;
            $warehouse = $po->inventory_warehouse_id ? $po->warehouse()->first() : null;

            if ($product && $warehouse) {
                $this->inventory->receive(
                    $product,
                    $warehouse,
                    $receiveQty,
                    (float) $item->unit_cost,
                    [
                        'actor_id' => $opts['actor_id'] ?? auth()->id(),
                        'reference_type' => 'procurement_purchase_order',
                        'reference_id' => (string) $po->id,
                        'note' => $opts['note'] ?? "PO {$po->number}",
                    ],
                );
            }

            $this->refreshStatus($po);

            return $item->fresh();
        });
    }

    /** Receive every line's full outstanding quantity in one go. */
    public function receiveAll(PurchaseOrder $po, array $opts = []): PurchaseOrder
    {
        foreach ($po->items()->get() as $item) {
            if ($item->outstanding() > 0) {
                $this->receiveItem($item, $item->outstanding(), $opts);
            }
        }

        return $po->fresh();
    }

    /** Drive status from line receipts: none → unchanged, some → partial, all → received. */
    private function refreshStatus(PurchaseOrder $po): void
    {
        $items = $po->items()->get();
        $totalOrdered = (float) $items->sum('quantity_ordered');
        $totalReceived = (float) $items->sum('quantity_received');

        if ($totalReceived <= 0 || $totalOrdered <= 0) {
            return;
        }

        $status = $items->every(fn (PurchaseOrderItem $i) => $i->isFullyReceived())
            ? PurchaseOrderStatus::Received
            : PurchaseOrderStatus::PartiallyReceived;

        $po->forceFill(['status' => $status])->save();
    }
}
