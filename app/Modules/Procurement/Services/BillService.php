<?php

namespace App\Modules\Procurement\Services;

use App\Modules\Procurement\Enums\BillPaymentApprovalStatus;
use App\Modules\Procurement\Enums\BillPaymentStatus;
use App\Modules\Procurement\Models\Bill;
use App\Modules\Procurement\Models\BillPayment;
use App\Modules\Procurement\Models\PurchaseOrder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Owns the bill (vendor invoice) stage after a PO: raise a bill from a purchase
 * order, record payments against it (with optional per-payment approval), keep
 * the paid amount / payment status in sync, and generate recurring bills.
 */
class BillService
{
    use ComputesDocumentTotals;

    public function __construct(private readonly ProcurementNumberService $numbers) {}

    /** Recompute line totals → subtotal → tax → total (incl. shipping and discount). */
    public function recalcTotals(Bill $bill): Bill
    {
        $roll = $this->rollupLines($bill->items()->get());
        $subtotal = $roll['subtotal'];
        $bill->forceFill([
            'subtotal' => $subtotal,
            'tax_amount' => $roll['tax'],
            'total' => round($subtotal + $roll['tax'] + (float) $bill->shipping_amount - (float) $bill->discount_total, 2),
        ])->save();

        $this->recomputePaymentStatus($bill);

        return $bill;
    }

    /** Raise a bill from a purchase order, copying its lines and vendor. */
    public function createFromPurchaseOrder(PurchaseOrder $po, int $actorId, array $opts = []): Bill
    {
        return DB::transaction(function () use ($po, $actorId, $opts) {
            $bill = Bill::create([
                'organization_id' => $po->organization_id,
                'created_by' => $actorId,
                'procurement_supplier_id' => $po->procurement_supplier_id,
                'procurement_purchase_order_id' => $po->id,
                'number' => $this->numbers->bill($po->organization_id),
                'vendor_invoice_number' => $opts['vendor_invoice_number'] ?? null,
                'bill_date' => $opts['bill_date'] ?? now()->toDateString(),
                'due_date' => $opts['due_date'] ?? null,
                'currency' => $po->currency,
                'tax_rate' => $po->tax_rate,
                'shipping_amount' => $po->shipping_amount,
                'payment_status' => BillPaymentStatus::Unpaid,
            ]);

            foreach ($po->items()->orderBy('position')->orderBy('id')->get() as $item) {
                $bill->items()->create([
                    'organization_id' => $po->organization_id,
                    'inventory_product_id' => $item->inventory_product_id,
                    'description' => $item->description,
                    'sku' => $item->sku,
                    'quantity' => $item->quantity_ordered,
                    'unit_cost' => $item->unit_cost,
                    'tax_rate' => 0,
                    'line_total' => round((float) $item->quantity_ordered * (float) $item->unit_cost, 2),
                    'position' => $item->position,
                ]);
            }

            $this->recalcTotals($bill);

            return $bill->fresh();
        });
    }

    /**
     * Record a payment against a bill. When $requireApproval is true the payment
     * is held Pending (it doesn't count toward the paid amount until approved);
     * otherwise it's Approved immediately.
     */
    public function recordPayment(Bill $bill, array $data, int $actorId, bool $requireApproval = false): BillPayment
    {
        return DB::transaction(function () use ($bill, $data, $actorId, $requireApproval) {
            $approved = ! $requireApproval;

            $payment = $bill->payments()->create([
                'organization_id' => $bill->organization_id,
                'amount' => round((float) $data['amount'], 2),
                'payment_method' => $data['payment_method'] ?? null,
                'paid_on' => $data['paid_on'] ?? now()->toDateString(),
                'reference' => $data['reference'] ?? null,
                'note' => $data['note'] ?? null,
                'approval_status' => $approved ? BillPaymentApprovalStatus::Approved : BillPaymentApprovalStatus::Pending,
                'requested_by' => $actorId,
                'recorded_by' => $actorId,
                'approved_by' => $approved ? $actorId : null,
                'approved_at' => $approved ? now() : null,
            ]);

            $this->recomputePaymentStatus($bill);

            return $payment;
        });
    }

    public function approvePayment(BillPayment $payment, int $approverId): BillPayment
    {
        $payment->forceFill([
            'approval_status' => BillPaymentApprovalStatus::Approved,
            'approved_by' => $approverId,
            'approved_at' => now(),
        ])->save();

        $this->recomputePaymentStatus($payment->bill()->first());

        return $payment;
    }

    public function rejectPayment(BillPayment $payment, int $approverId): BillPayment
    {
        $payment->forceFill([
            'approval_status' => BillPaymentApprovalStatus::Rejected,
            'approved_by' => $approverId,
            'approved_at' => now(),
        ])->save();

        $this->recomputePaymentStatus($payment->bill()->first());

        return $payment;
    }

    /** Sum approved payments into amount_paid and derive the payment status. */
    public function recomputePaymentStatus(Bill $bill): void
    {
        $paid = (float) $bill->payments()
            ->where('approval_status', BillPaymentApprovalStatus::Approved->value)
            ->sum('amount');

        $total = (float) $bill->total;
        $status = match (true) {
            $paid <= 0 => BillPaymentStatus::Unpaid,
            $paid + 0.001 >= $total => BillPaymentStatus::Paid,
            default => BillPaymentStatus::PartiallyPaid,
        };

        $bill->forceFill(['amount_paid' => round($paid, 2), 'payment_status' => $status])->save();
    }

    /**
     * Generate any recurring bills that are due: for each active template whose
     * next_recurring_date has arrived, clone it into a fresh bill, advance the
     * schedule, and stop once the cycle limit is reached. Returns the count made.
     */
    public function generateDueRecurring(?int $organizationId = null): int
    {
        $today = now()->toDateString();

        $templates = Bill::query()
            ->where('recurring', true)
            ->whereNotNull('next_recurring_date')
            ->whereDate('next_recurring_date', '<=', $today)
            ->when($organizationId, fn ($q) => $q->where('organization_id', $organizationId))
            ->get();

        $made = 0;
        foreach ($templates as $template) {
            if ($template->recurring_total_cycles > 0 && $template->recurring_cycles >= $template->recurring_total_cycles) {
                $template->forceFill(['next_recurring_date' => null])->save();
                continue;
            }

            $this->generateOneFrom($template);
            $made++;
        }

        return $made;
    }

    private function generateOneFrom(Bill $template): void
    {
        DB::transaction(function () use ($template) {
            $issueDate = Carbon::parse($template->next_recurring_date);

            $bill = Bill::create([
                'organization_id' => $template->organization_id,
                'created_by' => $template->created_by,
                'procurement_supplier_id' => $template->procurement_supplier_id,
                'procurement_purchase_order_id' => $template->procurement_purchase_order_id,
                'number' => $this->numbers->bill($template->organization_id),
                'bill_date' => $issueDate->toDateString(),
                'due_date' => $template->due_date ? $issueDate->copy()->addDays(
                    Carbon::parse($template->bill_date ?? $template->created_at)->diffInDays($template->due_date)
                )->toDateString() : null,
                'currency' => $template->currency,
                'tax_rate' => $template->tax_rate,
                'shipping_amount' => $template->shipping_amount,
                'discount_total' => $template->discount_total,
                'payment_status' => BillPaymentStatus::Unpaid,
                'recurring_parent_id' => $template->recurring_parent_id ?? $template->id,
                'notes' => $template->notes,
                'terms' => $template->terms,
            ]);

            foreach ($template->items()->orderBy('position')->orderBy('id')->get() as $item) {
                $bill->items()->create([
                    'organization_id' => $template->organization_id,
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

            $this->recalcTotals($bill);

            $template->forceFill([
                'recurring_cycles' => $template->recurring_cycles + 1,
                'next_recurring_date' => $this->advance($issueDate, $template->recurring_frequency)->toDateString(),
            ])->save();
        });
    }

    private function advance(Carbon $date, ?string $frequency): Carbon
    {
        return match ($frequency) {
            'weekly' => $date->copy()->addWeek(),
            'quarterly' => $date->copy()->addMonths(3),
            'yearly' => $date->copy()->addYear(),
            default => $date->copy()->addMonth(), // monthly
        };
    }
}
