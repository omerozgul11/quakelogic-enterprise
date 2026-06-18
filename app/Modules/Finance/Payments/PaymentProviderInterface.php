<?php

namespace App\Modules\Finance\Payments;

/**
 * A payment gateway. Implementations never store raw card data — they return a
 * hosted checkout URL / token. Mirrors AiProviderInterface: a live provider
 * without credentials reports isAvailable()=false and the factory degrades to
 * the fake provider.
 */
interface PaymentProviderInterface
{
    public function getName(): string;

    /** True only when the gateway is configured with working credentials. */
    public function isAvailable(): bool;

    /**
     * Create a checkout / payment intent for an amount. `idempotencyKey` is a
     * stable reference (e.g. the payment-intent ULID) so retries don't double-charge.
     *
     * @param array<string,mixed> $opts  description, return_url, customer info…
     */
    public function createCheckout(string $idempotencyKey, float $amount, string $currency, array $opts = []): PaymentResult;
}
