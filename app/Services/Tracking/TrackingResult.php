<?php

namespace App\Services\Tracking;

use App\Enums\MailingStatus;
use Illuminate\Support\Carbon;

/** Carrier-agnostic tracking snapshot returned by every CarrierTrackingClient. */
final readonly class TrackingResult
{
    /**
     * @param  TrackingEvent[]  $events  newest-first
     */
    public function __construct(
        public MailingStatus $status,
        public ?string $statusCode,
        public ?string $statusDescription,
        public ?Carbon $scheduledDelivery,
        public ?Carbon $deliveredAt,
        public ?string $receivedBy,
        public ?string $proofUrl,
        public array $events,
    ) {}
}
