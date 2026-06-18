<?php

namespace App\Modules\Finance\Payments;

/**
 * Deterministic gateway used in tests and by default. Returns a fake hosted
 * checkout URL; the payment is "captured" by the app via PaymentService (which
 * simulates the webhook), so the full collect → capture → mark-paid flow works
 * without any real provider account.
 */
class FakePaymentProvider implements PaymentProviderInterface
{
    public function getName(): string
    {
        return 'fake';
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function createCheckout(string $idempotencyKey, float $amount, string $currency, array $opts = []): PaymentResult
    {
        return new PaymentResult(
            provider: 'fake',
            reference: 'fake_'.$idempotencyKey,
            url: url('/finance/checkout/'.$idempotencyKey),
            status: 'pending',
            raw: ['amount' => $amount, 'currency' => $currency] + $opts,
        );
    }
}
