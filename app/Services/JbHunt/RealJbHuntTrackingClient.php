<?php

namespace App\Services\JbHunt;

use App\Enums\MailingStatus;
use App\Services\Tracking\CarrierTrackingClient;
use App\Services\Tracking\TrackingEvent;
use App\Services\Tracking\TrackingException;
use App\Services\Tracking\TrackingResult;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Live J.B. Hunt 360 freight tracking client (OAuth2 client-credentials). Bound
 * only when JBHUNT_SYNC_ENABLED=true and credentials are present (see
 * TrackingClientFactory).
 *
 * J.B. Hunt's 360 APIs sit behind a gateway and their exact tracking payload is
 * only documented to credentialed partners (developer.jbhunt.com). To avoid
 * baking in an unverified contract, the endpoint paths/auth are config-driven
 * (config/services.php → jbhunt) and the parser reads the response defensively:
 * it scans the common field shapes for the event list, location and timestamps,
 * and derives the status from milestone keywords. Once a sandbox response is in
 * hand, tighten parse()/resolveStatus() to the documented field names.
 */
class RealJbHuntTrackingClient implements CarrierTrackingClient
{
    public function __construct(
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $baseUrl,
        private readonly ?string $tokenUrl,
        private readonly ?string $scope,
        private readonly ?string $subscriptionKey,
        private readonly string $trackPath,
    ) {}

    public function track(string $trackingNumber): TrackingResult
    {
        try {
            $url = rtrim($this->baseUrl, '/').'/'.ltrim(
                str_replace('{tracking}', rawurlencode($trackingNumber), $this->trackPath),
                '/'
            );

            $response = Http::withToken($this->accessToken())
                ->withHeaders($this->gatewayHeaders())
                ->acceptJson()
                ->connectTimeout(5)
                ->timeout(12)
                ->get($url);

            if ($response->failed()) {
                // Auth/config problems and gateway outages are real failures —
                // surface them so the poll retries and misconfiguration is
                // visible. A 404 (PRO not ingested yet) just means there's no
                // data: record the shipment as pending, like the UPS client.
                if (in_array($response->status(), [401, 403], true) || $response->serverError()) {
                    throw new TrackingException("J.B. Hunt tracking request failed ({$response->status()}) for {$trackingNumber}.");
                }

                return new TrackingResult(
                    MailingStatus::LabelCreated,
                    null,
                    'Awaiting first J.B. Hunt scan.',
                    null, null, null, null, [],
                );
            }

            return $this->parse($response->json() ?? []);
        } catch (TrackingException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new TrackingException("J.B. Hunt tracking error for {$trackingNumber}: {$e->getMessage()}", previous: $e);
        }
    }

    /** @return array<string, string> */
    private function gatewayHeaders(): array
    {
        return $this->subscriptionKey
            ? ['Ocp-Apim-Subscription-Key' => $this->subscriptionKey, 'x-api-key' => $this->subscriptionKey]
            : [];
    }

    private function accessToken(): string
    {
        return Cache::remember('jbhunt:oauth_token', now()->addMinutes(50), function () {
            $tokenUrl = $this->tokenUrl ?: rtrim($this->baseUrl, '/').'/tokens/oauth2/v1';

            $payload = ['grant_type' => 'client_credentials'];
            if ($this->scope) {
                $payload['scope'] = $this->scope;
            }

            $response = Http::asForm()
                ->withHeaders($this->gatewayHeaders())
                ->connectTimeout(5)
                ->timeout(12)
                ->withBasicAuth($this->clientId, $this->clientSecret)
                ->post($tokenUrl, $payload);

            if ($response->failed()) {
                throw new TrackingException("J.B. Hunt OAuth token request failed ({$response->status()}).");
            }

            return (string) ($response->json('access_token') ?? $response->json('accessToken') ?? '');
        });
    }

    /**
     * Parse a J.B. Hunt tracking payload. Field names vary by API version, so we
     * look through the likely shapes rather than assuming one.
     *
     * @param  array<string, mixed>  $body
     */
    private function parse(array $body): TrackingResult
    {
        $shipment = data_get($body, 'shipment')
            ?? data_get($body, 'data.0')
            ?? data_get($body, 'shipments.0')
            ?? $body;

        $rawEvents = data_get($shipment, 'events')
            ?? data_get($shipment, 'statusHistory')
            ?? data_get($shipment, 'movements')
            ?? data_get($shipment, 'trackingEvents')
            ?? data_get($shipment, 'history')
            ?? data_get($shipment, 'milestones')
            ?? [];

        $events = [];
        foreach ($rawEvents as $e) {
            $desc = (string) (data_get($e, 'description')
                ?? data_get($e, 'statusDescription')
                ?? data_get($e, 'status')
                ?? data_get($e, 'event')
                ?? data_get($e, 'activityDescription')
                ?? 'Update');

            $when = $this->timestamp(
                data_get($e, 'dateTime')
                ?? data_get($e, 'eventDateTime')
                ?? data_get($e, 'timestamp')
                ?? data_get($e, 'date')
                ?? data_get($e, 'occurredAt')
            );

            $events[] = new TrackingEvent($this->codeFor($desc), $desc, $this->location($e), $when);
        }

        usort($events, fn ($a, $b) => $b->occurredAt <=> $a->occurredAt);

        $statusText = (string) (data_get($shipment, 'status')
            ?? data_get($shipment, 'currentStatus')
            ?? data_get($shipment, 'shipmentStatus')
            ?? data_get($shipment, 'statusDescription')
            ?? ($events[0]->description ?? ''));

        $status = $this->resolveStatus($statusText, $events);

        $scheduled = $this->date(
            data_get($shipment, 'estimatedDeliveryDate')
            ?? data_get($shipment, 'scheduledDelivery')
            ?? data_get($shipment, 'deliveryDate')
            ?? data_get($shipment, 'estimatedDelivery')
        );

        $deliveredAt = null;
        $receivedBy = null;
        if ($status === MailingStatus::Delivered) {
            $deliveredAt = $this->date(data_get($shipment, 'deliveredDate') ?? data_get($shipment, 'actualDeliveryDate'))
                ?? ($events[0]->occurredAt ?? Carbon::now());
            $receivedBy = data_get($shipment, 'receivedBy') ?? data_get($shipment, 'signedBy');
        }

        return new TrackingResult($status, null, $statusText ?: null, $scheduled, $deliveredAt, $receivedBy, null, $events);
    }

    /** Map a milestone description to the carrier-agnostic event code. */
    private function codeFor(string $description): ?string
    {
        $d = strtolower($description);

        return match (true) {
            str_contains($d, 'delivered') => 'D',
            str_contains($d, 'out for delivery') => 'O',
            str_contains($d, 'exception') || str_contains($d, 'appointment') || str_contains($d, 'hold') => 'X',
            str_contains($d, 'bill of lading') || str_contains($d, 'bol') || str_contains($d, 'booked') || str_contains($d, 'label') || str_contains($d, 'order received') => 'M',
            default => 'I',
        };
    }

    /**
     * Derive our status from the carrier's milestone text. Delivered/returned are
     * terminal — if any scan reports one, it wins.
     *
     * @param  TrackingEvent[]  $events
     */
    private function resolveStatus(string $statusText, array $events): MailingStatus
    {
        $haystacks = array_map(
            fn (string $s) => strtolower($s),
            array_merge([$statusText], array_map(fn (TrackingEvent $e) => $e->description, $events))
        );

        foreach ($haystacks as $h) {
            if (str_contains($h, 'delivered')) {
                return MailingStatus::Delivered;
            }
            if (str_contains($h, 'returned') || str_contains($h, 'refused')) {
                return MailingStatus::Returned;
            }
        }

        $latest = strtolower($statusText ?: ($events[0]->description ?? ''));

        return match (true) {
            str_contains($latest, 'out for delivery') => MailingStatus::OutForDelivery,
            str_contains($latest, 'exception') || str_contains($latest, 'appointment') || str_contains($latest, 'hold') || str_contains($latest, 'delay') => MailingStatus::Exception,
            str_contains($latest, 'bill of lading') || str_contains($latest, 'bol') || str_contains($latest, 'booked') || str_contains($latest, 'label') || str_contains($latest, 'order received') || str_contains($latest, 'pending pickup') => MailingStatus::LabelCreated,
            str_contains($latest, 'picked up') || str_contains($latest, 'in transit') || str_contains($latest, 'departed') || str_contains($latest, 'arrived') || str_contains($latest, 'en route') => MailingStatus::InTransit,
            default => MailingStatus::InTransit,
        };
    }

    /** @param array<string, mixed> $event */
    private function location(array $event): ?string
    {
        $city = data_get($event, 'city') ?? data_get($event, 'location.city') ?? data_get($event, 'address.city');
        $state = data_get($event, 'state') ?? data_get($event, 'stateProvince') ?? data_get($event, 'location.state') ?? data_get($event, 'address.state');
        $country = data_get($event, 'country') ?? data_get($event, 'location.country');

        $parts = array_filter([$city, $state, $country]);

        if ($parts) {
            return implode(', ', $parts);
        }

        $loc = data_get($event, 'location');

        return is_string($loc) && trim($loc) !== '' ? $loc : null;
    }

    private function timestamp(mixed $value): Carbon
    {
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
