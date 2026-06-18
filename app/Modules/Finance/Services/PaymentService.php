<?php

namespace App\Modules\Finance\Services;

use App\Models\Crm\Invoice;
use App\Models\Crm\Payment;
use App\Modules\Finance\Enums\PaymentIntentStatus;
use App\Modules\Finance\Models\PaymentIntent;
use App\Modules\Finance\Payments\PaymentProviderInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Online payment collection over the existing CRM invoices. createCheckout()
 * opens a gateway intent; capture() (the webhook/return handler — invoked
 * directly for the fake provider) records a completed crm_payments row and
 * re-syncs the invoice's paid state. No invoice/payment data is duplicated.
 */
class PaymentService
{
    public function __construct(private readonly PaymentProviderInterface $gateway) {}

    public function createCheckout(Invoice $invoice, float $amount, int $actorId): PaymentIntent
    {
        if ($amount <= 0) {
            throw new RuntimeException('Payment amount must be greater than zero.');
        }

        $intent = PaymentIntent::create([
            'organization_id' => $invoice->organization_id,
            'created_by' => $actorId,
            'crm_invoice_id' => $invoice->id,
            'provider' => $this->gateway->getName(),
            'amount' => $amount,
            'currency' => $invoice->currency ?? 'USD',
            'status' => PaymentIntentStatus::Pending,
        ]);

        $result = $this->gateway->createCheckout($intent->ulid, $amount, $intent->currency, [
            'description' => 'Invoice '.$invoice->number,
            'invoice_id' => $invoice->id,
        ]);

        $intent->update(['reference' => $result->reference, 'checkout_url' => $result->url]);

        return $intent->refresh();
    }

    /**
     * Settle an intent: record the payment against the invoice and move the
     * invoice toward Paid / Partially Paid. Idempotent — a non-pending intent
     * is returned untouched.
     */
    public function capture(PaymentIntent $intent, int $actorId): PaymentIntent
    {
        if ($intent->status !== PaymentIntentStatus::Pending) {
            return $intent;
        }

        return DB::transaction(function () use ($intent, $actorId) {
            $invoice = Invoice::where('organization_id', $intent->organization_id)->findOrFail($intent->crm_invoice_id);

            $payment = Payment::create([
                'organization_id' => $intent->organization_id,
                'created_by' => $actorId,
                'crm_invoice_id' => $invoice->id,
                'amount' => $intent->amount,
                'paid_at' => now()->toDateString(),
                'method' => 'Online ('.$intent->provider.')',
                'reference' => $intent->reference,
                'status' => 'completed',   // syncPaymentState() counts completed payments
            ]);

            $invoice->syncPaymentState();
            $invoice->save();

            $intent->update([
                'status' => PaymentIntentStatus::Paid,
                'paid_at' => now(),
                'crm_payment_id' => $payment->id,
            ]);

            return $intent->refresh();
        });
    }

    public function cancel(PaymentIntent $intent): PaymentIntent
    {
        if ($intent->status === PaymentIntentStatus::Pending) {
            $intent->update(['status' => PaymentIntentStatus::Cancelled]);
        }

        return $intent;
    }
}
