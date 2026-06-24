<?php

namespace App\Services\Rl;

use App\Enums\MailingStatus;
use App\Services\Tracking\CarrierTrackingClient;
use App\Services\Tracking\TrackingEvent;
use App\Services\Tracking\TrackingResult;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Deterministic, offline R+L Carriers freight client used by the test suite only
 * (see TrackingClientFactory — the real client always runs outside tests, since
 * R+L's public tracing page is reachable without an API key). Behaviour is keyed
 * off the PRO so tests can pin an outcome without hitting the network:
 *   *EXC* → exception · *BOL* → manifested only · *TRANSIT* → in transit ·
 *   *OFD* → out for delivery · *SLOW* → in transit, far-future ETA · (default) → delivered.
 */
class FakeRlCarriersTrackingClient implements CarrierTrackingClient
{
    public function track(string $trackingNumber): TrackingResult
    {
        $pro = strtoupper($trackingNumber);
        $now = Carbon::now();
        $events = [];

        $add = function (string $code, string $desc, ?string $loc, Carbon $at) use (&$events) {
            $events[] = new TrackingEvent($code, $desc, $loc, $at);
        };

        $add('M', 'Bill of lading created — shipment manifested with R+L Carriers.', null, $now->copy()->subDays(5));
        $add('P', 'Picked up', 'Wilmington, OH, US', $now->copy()->subDays(4)->setTime(14, 5));
        $add('I', 'Departed service center', 'Wilmington, OH, US', $now->copy()->subDays(4)->setTime(20, 30));
        $add('I', 'Arrived at service center', 'Atlanta, GA, US', $now->copy()->subDays(2)->setTime(7, 12));

        if (Str::contains($pro, 'EXC')) {
            $add('X', 'Exception: delivery appointment required at consignee.', 'Atlanta, GA, US', $now->copy()->subHours(6));

            return $this->result(MailingStatus::Exception, 'X', 'Exception', $now->copy()->addDays(2), null, $events);
        }

        if (Str::contains($pro, 'BOL')) {
            return $this->result(MailingStatus::LabelCreated, 'M', 'Bill of lading created', $now->copy()->addDays(4), null, array_slice($events, 0, 1));
        }

        if (Str::contains($pro, 'SLOW')) {
            return $this->result(MailingStatus::InTransit, 'I', 'In transit', $now->copy()->addDays(12), null, $events);
        }

        if (Str::contains($pro, 'TRANSIT')) {
            return $this->result(MailingStatus::InTransit, 'I', 'In transit', $now->copy()->addDays(3), null, $events);
        }

        if (Str::contains($pro, 'OFD')) {
            $add('O', 'Out for delivery', 'Atlanta, GA, US', $now->copy()->setTime(7, 30));

            return $this->result(MailingStatus::OutForDelivery, 'O', 'Out for delivery', $now->copy(), null, $events);
        }

        $deliveredAt = $now->copy()->subHours(3);
        $add('O', 'Out for delivery', 'Atlanta, GA, US', $now->copy()->subHours(8));
        $add('D', 'Delivered', 'Atlanta, GA, US', $deliveredAt);

        return $this->result(MailingStatus::Delivered, 'D', 'Delivered', $deliveredAt->copy()->startOfDay(), $deliveredAt, $events);
    }

    private function result(MailingStatus $status, string $code, string $desc, ?Carbon $scheduled, ?Carbon $deliveredAt, array $events): TrackingResult
    {
        usort($events, fn ($a, $b) => $b->occurredAt <=> $a->occurredAt);

        return new TrackingResult($status, $code, $desc, $scheduled, $deliveredAt, $deliveredAt ? 'RECEIVING DOCK' : null, null, $events);
    }
}
