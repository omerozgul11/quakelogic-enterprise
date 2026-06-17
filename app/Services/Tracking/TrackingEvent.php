<?php

namespace App\Services\Tracking;

use Illuminate\Support\Carbon;

/** A single carrier-agnostic tracking scan/update. */
final readonly class TrackingEvent
{
    public function __construct(
        public ?string $code,
        public string $description,
        public ?string $location,
        public Carbon $occurredAt,
    ) {}
}
