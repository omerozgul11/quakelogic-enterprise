<?php

namespace App\Modules\Finance\Payments;

use RuntimeException;

/** PayPal gateway stub. See StripePaymentProvider for the integration pattern. */
class PayPalPaymentProvider implements PaymentProviderInterface
{
    public function getName(): string
    {
        return 'paypal';
    }

    public function isAvailable(): bool
    {
        return ! empty(config('finance.providers.paypal.client_id')) && ! empty(config('finance.providers.paypal.secret'));
    }

    public function createCheckout(string $idempotencyKey, float $amount, string $currency, array $opts = []): PaymentResult
    {
        throw new RuntimeException('PayPal integration is not implemented yet. Set PAYMENT_PROVIDER=fake, or implement PayPalPaymentProvider::createCheckout().');
    }
}
