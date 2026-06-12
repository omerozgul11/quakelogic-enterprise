<?php

namespace App\Services\Tracking;

use App\Enums\Carrier;

/**
 * Placeholder for carriers without a live integration yet (FedEx, DHL). It
 * fails loudly so a mailing on an unimplemented carrier is never silently
 * treated as untracked — replace by registering a real client in
 * TrackingClientFactory when the integration lands.
 */
final class UnsupportedCarrierClient implements CarrierTrackingClient
{
    public function __construct(private readonly Carrier $carrier) {}

    public function track(string $trackingNumber): TrackingResult
    {
        throw new TrackingException("{$this->carrier->label()} tracking is not available yet.");
    }
}
