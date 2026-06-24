<?php

namespace App\Services\Tracking;

/**
 * Placeholder for carriers without a live integration (FedEx, DHL, and any
 * custom/freight carrier entered by hand, e.g. J.B. Hunt). It fails loudly so a
 * mailing on such a carrier is never silently treated as UPS — the user tracks
 * it manually (set status, upload the bill of lading / labels) instead. Register
 * a real client in TrackingClientFactory when an integration lands.
 */
final class UnsupportedCarrierClient implements CarrierTrackingClient
{
    public function __construct(private readonly string $carrierLabel) {}

    public function track(string $trackingNumber): TrackingResult
    {
        throw new TrackingException("Live tracking isn't available for {$this->carrierLabel} — update its status manually.");
    }
}
