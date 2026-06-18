<?php

namespace App\Modules\Finance\Payments;

use RuntimeException;

/** Square gateway stub. See StripePaymentProvider for the integration pattern. */
class SquarePaymentProvider implements PaymentProviderInterface
{
    public function getName(): string
    {
        return 'square';
    }

    public function isAvailable(): bool
    {
        return ! empty(config('finance.providers.square.access_token'));
    }

    public function createCheckout(string $idempotencyKey, float $amount, string $currency, array $opts = []): PaymentResult
    {
        throw new RuntimeException('Square integration is not implemented yet. Set PAYMENT_PROVIDER=fake, or implement SquarePaymentProvider::createCheckout().');
    }
}
