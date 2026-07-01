<?php

namespace App\Services\Dhl;

use App\Enums\MailingStatus;
use App\Services\Tracking\TrackingEvent;
use App\Services\Tracking\TrackingResult;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Maps a DHL "Shipment Tracking - Unified" shipment object onto our carrier-
 * agnostic TrackingResult. The push notification (webhook) and the request/
 * response tracking API share the SAME shipment schema
 * (developer.dhl.com/api-reference/shipment-tracking), so this one mapper serves
 * both: the pull API returns a full `events[]` history, while a push carries only
 * the latest `status` object — either way we produce the same shape.
 *
 * DHL's coarse statusCode enum is pre-transit | transit | delivered | failure |
 * unknown; finer states (out for delivery, returned) live in the free-text
 * `status`/`description`, so we refine the status from the text.
 */
class DhlShipmentMapper
{
    /** @param array<string,mixed> $shipment */
    public function toResult(array $shipment): TrackingResult
    {
        $statusNode = is_array(data_get($shipment, 'status')) ? (array) data_get($shipment, 'status') : [];
        $currentText = trim(((string) data_get($statusNode, 'status', '')).' '.((string) data_get($statusNode, 'description', '')));
        $status = $this->mapStatus(data_get($statusNode, 'statusCode'), $currentText);

        // Full history when present (pull API); otherwise synthesize a single event
        // from the current status (push notifications carry only the latest status).
        $events = [];
        $rawEvents = data_get($shipment, 'events');
        if (is_array($rawEvents) && $rawEvents !== []) {
            foreach ($rawEvents as $e) {
                if (is_array($e)) {
                    $events[] = $this->event($e);
                }
            }
        } elseif ($statusNode !== []) {
            $events[] = $this->event($statusNode);
        }

        usort($events, fn (TrackingEvent $a, TrackingEvent $b) => $b->occurredAt <=> $a->occurredAt);

        // A terminal scan in the history wins (delivered/returned are final).
        $status = $this->reconcile($status, $events);

        $scheduled = $this->date(
            data_get($shipment, 'estimatedTimeOfDelivery')
            ?? data_get($shipment, 'estimatedDeliveryTimeFrame.estimatedThrough')
            ?? data_get($shipment, 'estimatedDeliveryTimeFrame.estimatedFrom')
        );

        $deliveredAt = null;
        $receivedBy = null;
        if ($status === MailingStatus::Delivered) {
            $deliveredAt = $this->timestamp(data_get($statusNode, 'timestamp') ?? ($events[0]->occurredAt ?? null));
            $signee = data_get($shipment, 'details.proofOfDelivery.signee')
                ?? data_get($statusNode, 'signee')
                ?? data_get($shipment, 'details.receiver.name');
            $receivedBy = is_string($signee) && trim($signee) !== '' ? $signee : null;
        }

        $statusCode = data_get($statusNode, 'statusCode');

        return new TrackingResult(
            $status,
            is_string($statusCode) && $statusCode !== '' ? $statusCode : null,
            $currentText !== '' ? $currentText : null,
            $scheduled,
            $deliveredAt,
            $receivedBy,
            null,
            $events,
        );
    }

    /**
     * DHL statusCode → our status, refined by the free-text status/description
     * (DHL only exposes out-for-delivery and returned in the text).
     */
    public function mapStatus(mixed $statusCode, string $text = ''): MailingStatus
    {
        $code = strtolower(trim((string) $statusCode));
        $t = strtolower($text);

        if (str_contains($t, 'returned') || str_contains($t, 'return to sender') || str_contains($t, 'refused')) {
            return MailingStatus::Returned;
        }

        return match ($code) {
            'pre-transit', 'pretransit', 'label', 'manifest' => MailingStatus::LabelCreated,
            'delivered' => MailingStatus::Delivered,
            'failure', 'exception' => MailingStatus::Exception,
            'transit', 'in-transit', 'intransit' => $this->isOutForDelivery($t)
                ? MailingStatus::OutForDelivery
                : MailingStatus::InTransit,
            default => $this->fromText($t),
        };
    }

    /** Carrier-agnostic event code, aligned with MailingStatus::fromUpsCode + the M/MP label-created lookup. */
    public function codeFor(MailingStatus $status): string
    {
        return match ($status) {
            MailingStatus::LabelCreated => 'M',
            MailingStatus::InTransit => 'I',
            MailingStatus::OutForDelivery => 'O',
            MailingStatus::Delivered => 'D',
            MailingStatus::Exception => 'X',
            MailingStatus::Returned => 'RS',
        };
    }

    /** @param array<string,mixed> $node a DHL `status` or `events[]` entry */
    private function event(array $node): TrackingEvent
    {
        $desc = data_get($node, 'description') ?? data_get($node, 'status') ?? data_get($node, 'remark') ?? 'Update';
        $desc = (string) $desc;
        $status = $this->mapStatus(
            data_get($node, 'statusCode'),
            trim($desc.' '.(string) data_get($node, 'status', '')),
        );

        return new TrackingEvent(
            $this->codeFor($status),
            $desc,
            $this->location($node),
            $this->timestamp(data_get($node, 'timestamp')),
        );
    }

    private function isOutForDelivery(string $t): bool
    {
        return str_contains($t, 'out for delivery')
            || str_contains($t, 'with delivery courier')
            || str_contains($t, 'on vehicle for delivery')
            || str_contains($t, 'loaded onto delivery vehicle');
    }

    private function fromText(string $t): MailingStatus
    {
        return match (true) {
            str_contains($t, 'delivered') => MailingStatus::Delivered,
            $this->isOutForDelivery($t) => MailingStatus::OutForDelivery,
            str_contains($t, 'exception') || str_contains($t, 'failure') || str_contains($t, 'held') || str_contains($t, 'not delivered') => MailingStatus::Exception,
            str_contains($t, 'label') || str_contains($t, 'manifest') || str_contains($t, 'data received') || str_contains($t, 'pre-transit') || str_contains($t, 'awaiting') => MailingStatus::LabelCreated,
            default => MailingStatus::InTransit,
        };
    }

    /** @param TrackingEvent[] $events */
    private function reconcile(MailingStatus $status, array $events): MailingStatus
    {
        foreach ($events as $e) {
            if ($e->code === 'D') {
                return MailingStatus::Delivered;
            }
            if ($e->code === 'RS') {
                return MailingStatus::Returned;
            }
        }

        return $status;
    }

    /** @param array<string,mixed> $node */
    private function location(array $node): ?string
    {
        $addr = data_get($node, 'location.address') ?? data_get($node, 'address');
        if (! is_array($addr)) {
            $loc = data_get($node, 'location');

            return is_string($loc) && trim($loc) !== '' ? $loc : null;
        }

        $parts = array_filter([
            data_get($addr, 'addressLocality'),
            data_get($addr, 'postalCode'),
            data_get($addr, 'countryCode'),
        ], fn ($v) => is_string($v) && trim($v) !== '');

        return $parts !== [] ? implode(', ', $parts) : null;
    }

    private function timestamp(mixed $value): Carbon
    {
        if ($value instanceof Carbon) {
            return $value;
        }
        if (! is_string($value) || trim($value) === '') {
            return Carbon::now();
        }
        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return Carbon::now();
        }
    }

    private function date(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }
        try {
            return Carbon::parse($value)->startOfDay();
        } catch (Throwable) {
            return null;
        }
    }
}
