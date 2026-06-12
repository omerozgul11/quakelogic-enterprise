<?php

namespace App\Services\Tracking;

use App\Enums\Carrier;
use App\Services\Ups\FakeUpsTrackingClient;
use App\Services\Ups\RealUpsTrackingClient;

/**
 * Resolves the right tracking client for a carrier. This is the ONE place to
 * register a new carrier — add a match arm returning its client (e.g.
 * Carrier::Fedex => new FedexTrackingClient(...)). UPS uses the real client
 * only when enabled + credentialed; otherwise the fake drives dev + tests.
 */
class TrackingClientFactory
{
    public function for(Carrier|string $carrier): CarrierTrackingClient
    {
        $carrier = $carrier instanceof Carrier
            ? $carrier
            : (Carrier::tryFrom($carrier) ?? Carrier::Ups);

        return match ($carrier) {
            Carrier::Ups => $this->ups(),
            default => new UnsupportedCarrierClient($carrier),
        };
    }

    private function ups(): CarrierTrackingClient
    {
        $ups = config('services.ups');

        if ($ups['sync_enabled'] && $ups['client_id'] && $ups['client_secret']) {
            return new RealUpsTrackingClient($ups['client_id'], $ups['client_secret'], $ups['base_url']);
        }

        return new FakeUpsTrackingClient();
    }
}
