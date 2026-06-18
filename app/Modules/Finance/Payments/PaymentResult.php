<?php

namespace App\Modules\Finance\Payments;

/**
 * Outcome of creating a checkout / payment with a gateway. `reference` is the
 * gateway's id for the transaction, `url` is the hosted payment page (if any).
 */
class PaymentResult
{
    /** @param array<string,mixed> $raw */
    public function __construct(
        public readonly string $provider,
        public readonly string $reference,
        public readonly ?string $url,
        public readonly string $status,
        public readonly array $raw = [],
    ) {}
}
