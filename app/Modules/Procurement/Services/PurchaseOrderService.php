<?php

namespace App\Modules\Procurement\Services;

use App\Models\User;
use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Procurement\Enums\PurchaseOrderStatus;
use App\Modules\Procurement\Mail\PurchaseOrderMail;
use App\Modules\Procurement\Models\PurchaseOrder;
use App\Modules\Procurement\Models\PurchaseOrderItem;
use App\Modules\Procurement\Models\SupplierContact;
use App\Notifications\ActivityNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use RuntimeException;

/**
 * Owns the purchase-order lifecycle and goods receipt. Receiving a line that is
 * linked to an inventory product (on a PO with a destination warehouse) posts a
 * stock receipt through InventoryService — the one integration point between
 * Procurement and Inventory.
 */
class PurchaseOrderService
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly ProcurementNumberService $numbers,
    ) {}

    /**
     * Raise a draft purchase order for a chosen vendor from a CRM sales invoice
     * or estimate, copying its line items. The CRM document is read-only.
     */
    public function fromCrmInvoice(\App\Models\Crm\Invoice $invoice, int $supplierId, int $actorId): PurchaseOrder
    {
        return DB::transaction(function () use ($invoice, $supplierId, $actorId) {
            $po = PurchaseOrder::create([
                'organization_id' => $invoice->organization_id,
                'created_by' => $actorId,
                'procurement_supplier_id' => $supplierId,
                'crm_project_id' => $invoice->crm_project_id,
                'number' => $this->numbers->generate($invoice->organization_id),
                'status' => PurchaseOrderStatus::Draft,
                'order_date' => now()->toDateString(),
                'currency' => $invoice->currency ?: 'USD',
                'tax_rate' => 0,
                'shipping_amount' => 0,
                'notes' => 'From '.($invoice->isEstimate() ? 'estimate' : 'invoice').' '.$invoice->number,
            ]);

            foreach ($invoice->items()->get() as $i) {
                $po->items()->create([
                    'organization_id' => $invoice->organization_id,
                    'description' => $i->description,
                    'quantity_ordered' => $i->quantity,
                    'unit_cost' => $i->unit_price,
                    'line_total' => round((float) $i->quantity * (float) $i->unit_price, 2),
                    'position' => $i->position,
                ]);
            }

            $this->recalcTotals($po);

            return $po->fresh();
        });
    }

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
        $this->notifyApprovers($po);

        return $po;
    }

    /**
     * Fire the "purchase order created" notices: email the supplier a copy of
     * the PO and send the internal buyer (its creator) an in-app + email
     * confirmation. Best-effort — a mail failure is logged and never blocks the
     * request. Call this after the create transaction has committed.
     */
    public function notifyCreated(PurchaseOrder $po): void
    {
        $this->emailSupplierCopy($po);

        $creator = $po->creator()->first();
        if ($creator) {
            try {
                $creator->notify(new ActivityNotification([
                    'type' => 'procurement',
                    'title' => "Purchase order {$po->number} created",
                    'message' => $this->summaryLine($po),
                    'url' => route('procurement.purchase-orders.show', $po),
                    'icon' => 'shopping-cart',
                    'email' => true,
                ]));
            } catch (\Throwable $e) {
                Log::warning('Purchase order creator notification failed', ['po' => $po->number, 'error' => $e->getMessage()]);
            }
        }
    }

    /** Email the supplier (or its primary contact) a copy of the PO. */
    private function emailSupplierCopy(PurchaseOrder $po): void
    {
        $email = $this->vendorEmail($po);
        if (! $email) {
            return;
        }

        try {
            Mail::to($email)->send(new PurchaseOrderMail($po));
            $po->forceFill(['emailed_at' => now()])->save();
        } catch (\Throwable $e) {
            Log::warning('Purchase order supplier email failed', ['po' => $po->number, 'error' => $e->getMessage()]);
        }
    }

    /** In-app + email alert to everyone who can approve, that a PO is waiting. */
    private function notifyApprovers(PurchaseOrder $po): void
    {
        $approvers = User::query()
            ->where('organization_id', $po->organization_id)
            ->permission('approve purchase orders')
            ->get();

        if ($approvers->isEmpty()) {
            return;
        }

        try {
            Notification::send($approvers, new ActivityNotification([
                'type' => 'procurement',
                'title' => "Approval needed: purchase order {$po->number}",
                'message' => $this->summaryLine($po).' — submitted for approval.',
                'url' => route('procurement.purchase-orders.show', $po),
                'icon' => 'shopping-cart',
                'email' => true,
            ]));
        } catch (\Throwable $e) {
            Log::warning('Purchase order approver notification failed', ['po' => $po->number, 'error' => $e->getMessage()]);
        }
    }

    /** Short one-line summary of a PO for notification bodies. */
    private function summaryLine(PurchaseOrder $po): string
    {
        $supplier = $po->supplier()->value('name') ?? 'Supplier';

        return $supplier.' — '.number_format((float) $po->total, 2).' '.$po->currency;
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

    /**
     * Mark the PO as sent and email it to the supplier (its email, or the
     * primary contact's). The status advances even if the email can't be sent
     * (logged) so the workflow never gets stuck on a mail hiccup.
     */
    public function markSent(PurchaseOrder $po): PurchaseOrder
    {
        $email = $this->vendorEmail($po);
        $emailedAt = null;

        if ($email) {
            try {
                Mail::to($email)->send(new PurchaseOrderMail($po));
                $emailedAt = now();
            } catch (\Throwable $e) {
                Log::warning('Purchase order vendor email failed', ['po' => $po->number, 'error' => $e->getMessage()]);
            }
        }

        $attrs = ['status' => PurchaseOrderStatus::Sent];
        if ($emailedAt !== null) {
            $attrs['emailed_at'] = $emailedAt;
        }
        $po->forceFill($attrs)->save();

        return $po;
    }

    /**
     * Stamp that the PO was emailed (via the rich "Send to vendor" modal, which
     * does its own sending). A draft/approved PO advances to Sent; a PO already
     * further along (received etc.) keeps its status.
     */
    public function markEmailed(PurchaseOrder $po): PurchaseOrder
    {
        $attrs = ['emailed_at' => now()];
        if (in_array($po->status, [PurchaseOrderStatus::Draft, PurchaseOrderStatus::Approved], true)) {
            $attrs['status'] = PurchaseOrderStatus::Sent;
        }
        $po->forceFill($attrs)->save();

        return $po;
    }

    /** The supplier's email, or its primary contact's, or null if none on file. */
    public function vendorEmail(PurchaseOrder $po): ?string
    {
        $supplier = $po->supplier()->first();
        if (! $supplier) {
            return null;
        }
        if ($supplier->email) {
            return $supplier->email;
        }

        return SupplierContact::where('procurement_supplier_id', $supplier->id)
            ->orderByDesc('is_primary')->orderBy('id')->value('email');
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
