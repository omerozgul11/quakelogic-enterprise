<?php

namespace App\Services\Ups;

use App\Enums\MailingStatus;
use App\Services\Tracking\CarrierTrackingClient;
use App\Services\Tracking\TrackingEvent;
use App\Services\Tracking\TrackingResult;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Deterministic, offline UPS client used whenever UPS_SYNC_ENABLED=false
 * (dev + the entire test suite) — no real API calls. Behaviour is driven by
 * keywords in the tracking number so tests can pin an outcome without mocking:
 *   *EXC* → exception · *LABEL* → label only · *TRANSIT* → in transit ·
 *   *OFD* → out for delivery · *SLOW* → in transit, far-future ETA (at risk) ·
 *   (default) → delivered with proof of delivery.
 */
class FakeUpsTrackingClient implements CarrierTrackingClient
{
    public function track(string $trackingNumber): TrackingResult
    {
        $tn = strtoupper($trackingNumber);
        $now = Carbon::now();
        $events = [];

        $add = function (string $code, string $desc, ?string $loc, Carbon $at) use (&$events) {
            $events[] = new TrackingEvent($code, $desc, $loc, $at);
        };

        $add('M', 'Shipper created a label, UPS has not received the package yet.', null, $now->copy()->subDays(4));
        $add('I', 'Origin Scan', 'Louisville, KY, US', $now->copy()->subDays(3)->setTime(20, 14));
        $add('I', 'Departed from Facility', 'Louisville, KY, US', $now->copy()->subDays(3)->setTime(23, 2));
        $add('I', 'Arrived at Facility', 'Philadelphia, PA, US', $now->copy()->subDays(1)->setTime(4, 31));

        if (Str::contains($tn, 'EXC')) {
            $add('X', 'Exception: The receiver was not available. A second delivery attempt will be made.', 'Philadelphia, PA, US', $now->copy()->subHours(5));

            return $this->result(MailingStatus::Exception, 'X', 'Exception', $now->copy()->addDay(), null, null, null, $events);
        }

        if (Str::contains($tn, 'LABEL')) {
            return $this->result(MailingStatus::LabelCreated, 'M', 'Label Created', $now->copy()->addDays(3), null, null, null, array_slice($events, 0, 1));
        }

        if (Str::contains($tn, 'SLOW')) {
            return $this->result(MailingStatus::InTransit, 'I', 'In Transit', $now->copy()->addDays(10), null, null, null, $events);
        }

        if (Str::contains($tn, 'TRANSIT')) {
            return $this->result(MailingStatus::InTransit, 'I', 'In Transit', $now->copy()->addDays(2), null, null, null, $events);
        }

        if (Str::contains($tn, 'OFD')) {
            $add('O', 'Out For Delivery Today', 'Philadelphia, PA, US', $now->copy()->setTime(6, 2));

            return $this->result(MailingStatus::OutForDelivery, 'O', 'Out For Delivery', $now->copy(), null, null, null, $events);
        }

        $deliveredAt = $now->copy()->subHours(3);
        $add('O', 'Out For Delivery Today', 'Philadelphia, PA, US', $now->copy()->subHours(6));
        $add('D', 'Delivered', 'Philadelphia, PA, US', $deliveredAt);

        return $this->result(
            MailingStatus::Delivered,
            'D',
            'Delivered',
            $deliveredAt->copy()->startOfDay(),
            $deliveredAt,
            'FRONT DESK',
            'https://www.ups.com/track?loc=en_US&tracknum='.$trackingNumber,
            $events,
        );
    }

    private function result(
        MailingStatus $status,
        string $code,
        string $desc,
        ?Carbon $scheduled,
        ?Carbon $deliveredAt,
        ?string $receivedBy,
        ?string $proofUrl,
        array $events,
    ): TrackingResult {
        usort($events, fn ($a, $b) => $b->occurredAt <=> $a->occurredAt);

        return new TrackingResult($status, $code, $desc, $scheduled, $deliveredAt, $receivedBy, $proofUrl, $events);
    }
}
