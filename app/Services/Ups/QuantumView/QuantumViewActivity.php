<?php

namespace App\Services\Ups\QuantumView;

use Illuminate\Support\Carbon;

/** One normalized Quantum View activity for a shipment on the account. */
final readonly class QuantumViewActivity
{
    /**
     * @param  'manifest'|'origin'|'delivery'|'exception'  $type
     * @param  string[]  $references  customer/shipment reference numbers (e.g. a proposal number)
     */
    public function __construct(
        public string $trackingNumber,
        public string $type,
        public ?Carbon $scheduledDelivery,
        public ?Carbon $occurredAt,
        public ?string $description,
        public ?string $location,
        public ?string $recipientName,
        public array $references = [],
    ) {}
}
