<?php

namespace App\Modules\Finance\Payments;

use RuntimeException;

/**
 * Stripe gateway. Wiring to the Stripe API is deferred — isAvailable() reflects
 * whether credentials are configured; until the live integration lands the
 * factory falls back to the fake provider when no key is set. With a key set,
 * createCheckout() throws clearly rather than silently failing.
 */
class StripePaymentProvider implements PaymentProviderInterface
{
    public function getName(): string
    {
        return 'stripe';
    }

    public function isAvailable(): bool
    {
        return ! empty(config('finance.providers.stripe.secret'));
    }

    public function createCheckout(string $idempotencyKey, float $amount, string $currency, array $opts = []): PaymentResult
    {
        throw new RuntimeException('Stripe Checkout integration is not implemented yet. Set PAYMENT_PROVIDER=fake, or implement StripePaymentProvider::createCheckout().');
    }
}
