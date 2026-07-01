<?php

namespace App\Services\Dhl;

use App\Enums\MailingStatus;
use App\Services\Tracking\CarrierTrackingClient;
use App\Services\Tracking\TrackingEvent;
use App\Services\Tracking\TrackingResult;
use Illuminate\Support\Carbon;

/**
 * Deterministic DHL tracking simulator. Only ever bound in the test suite (gated
 * on runningUnitTests() in TrackingClientFactory) — on a real deployment DHL
 * stays push-driven/manual until DHL_API_KEY is present, so we never fabricate
 * shipment data in dev or production.
 */
class FakeDhlTrackingClient implements CarrierTrackingClient
{
    public function track(string $trackingNumber): TrackingResult
    {
        return new TrackingResult(
            MailingStatus::InTransit,
            'transit',
            'Processed at DHL facility',
            Carbon::now()->addDays(2)->startOfDay(),
            null,
            null,
            null,
            [
                new TrackingEvent('I', 'Processed at DHL facility', 'Leipzig, DE', Carbon::now()->subDay()),
                new TrackingEvent('M', 'Shipment information received', 'Cincinnati, US', Carbon::now()->subDays(2)),
            ],
        );
    }
}
