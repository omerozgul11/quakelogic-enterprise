<?php

namespace App\Services\Tracking;

interface CarrierTrackingClient
{
    /**
     * Fetch the current tracking state for a tracking number.
     *
     * @throws TrackingException on transport/auth/parse failure.
     */
    public function track(string $trackingNumber): TrackingResult;
}
