<?php

namespace App\Services\JbHunt;

use App\Enums\MailingStatus;
use App\Services\Tracking\CarrierTrackingClient;
use App\Services\Tracking\TrackingEvent;
use App\Services\Tracking\TrackingResult;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Deterministic, offline J.B. Hunt freight client used whenever
 * JBHUNT_SYNC_ENABLED=false in a non-production environment (dev + the test
 * suite) — no real API calls. Behaviour is driven by keywords in the PRO/BOL
 * number so tests can pin an outcome without mocking:
 *   *EXC* → exception · *BOL* → booked only · *TRANSIT* → in transit ·
 *   *OFD* → out for delivery · *SLOW* → in transit, far-future ETA (at risk) ·
 *   (default) → delivered.
 *
 * The booking/label event is emitted with code 'M' (the carrier-agnostic
 * "label/booking created" marker) so it feeds the label-created column the same
 * way UPS does.
 */
class FakeJbHuntTrackingClient implements CarrierTrackingClient
{
    public function track(string $trackingNumber): TrackingResult
    {
        $tn = strtoupper($trackingNumber);
        $now = Carbon::now();
        $events = [];

        $add = function (string $code, string $desc, ?string $loc, Carbon $at) use (&$events) {
            $events[] = new TrackingEvent($code, $desc, $loc, $at);
        };

        $add('M', 'Bill of lading created — shipment booked with J.B. Hunt.', null, $now->copy()->subDays(5));
        $add('P', 'Picked up', 'Lowell, AR, US', $now->copy()->subDays(4)->setTime(15, 10));
        $add('I', 'Departed service center', 'Lowell, AR, US', $now->copy()->subDays(4)->setTime(21, 40));
        $add('I', 'Arrived at service center', 'Atlanta, GA, US', $now->copy()->subDays(2)->setTime(6, 18));

        if (Str::contains($tn, 'EXC')) {
            $add('X', 'Exception: delivery appointment required at consignee.', 'Atlanta, GA, US', $now->copy()->subHours(6));

            return $this->result(MailingStatus::Exception, 'X', 'Exception', $now->copy()->addDays(2), null, null, $events);
        }

        if (Str::contains($tn, 'BOL')) {
            return $this->result(MailingStatus::LabelCreated, 'M', 'Bill of lading created', $now->copy()->addDays(4), null, null, array_slice($events, 0, 1));
        }

        if (Str::contains($tn, 'SLOW')) {
            return $this->result(MailingStatus::InTransit, 'I', 'In transit', $now->copy()->addDays(12), null, null, $events);
        }

        if (Str::contains($tn, 'TRANSIT')) {
            return $this->result(MailingStatus::InTransit, 'I', 'In transit', $now->copy()->addDays(3), null, null, $events);
        }

        if (Str::contains($tn, 'OFD')) {
            $add('O', 'Out for delivery', 'Atlanta, GA, US', $now->copy()->setTime(7, 5));

            return $this->result(MailingStatus::OutForDelivery, 'O', 'Out for delivery', $now->copy(), null, null, $events);
        }

        $deliveredAt = $now->copy()->subHours(4);
        $add('O', 'Out for delivery', 'Atlanta, GA, US', $now->copy()->subHours(8));
        $add('D', 'Delivered', 'Atlanta, GA, US', $deliveredAt);

        return $this->result(
            MailingStatus::Delivered,
            'D',
            'Delivered',
            $deliveredAt->copy()->startOfDay(),
            $deliveredAt,
            'RECEIVING DOCK',
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
        array $events,
    ): TrackingResult {
        usort($events, fn ($a, $b) => $b->occurredAt <=> $a->occurredAt);

        return new TrackingResult($status, $code, $desc, $scheduled, $deliveredAt, $receivedBy, null, $events);
    }
}
