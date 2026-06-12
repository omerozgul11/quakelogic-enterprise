<?php

namespace App\Services\Ups\QuantumView;

use App\Services\Tracking\TrackingException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Live UPS Quantum View client (POST /api/quantumview/v3/events). Pulls account
 * shipment events (manifest / origin / delivery / exception) for an outbound
 * subscription. Only bound when UPS_QV_ENABLED=true + credentials + subscription
 * are present. Untested against the live API until the Quantum View product is
 * enabled on the UPS app — kept behind the flag by design.
 */
class UpsQuantumViewClient implements QuantumViewClient
{
    public function __construct(
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $baseUrl,
        private readonly ?string $subscription,
    ) {}

    public function fetch(Carbon $since): array
    {
        try {
            $body = [
                'QuantumViewRequest' => [
                    'Request' => ['TransactionReference' => ['CustomerContext' => 'quakelogic-shipments']],
                    'SubscriptionRequest' => array_filter([
                        'Name' => $this->subscription,
                        'DateTimeRange' => [
                            'BeginDateTime' => $since->copy()->format('YmdHis'),
                            'EndDateTime' => Carbon::now()->format('YmdHis'),
                        ],
                    ]),
                ],
            ];

            $response = Http::withToken($this->accessToken())
                ->acceptJson()
                ->post(rtrim($this->baseUrl, '/').'/quantumview/v3/events', $body);

            if ($response->failed()) {
                throw new TrackingException("Quantum View request failed ({$response->status()}).");
            }

            return $this->parse($response->json());
        } catch (TrackingException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new TrackingException('Quantum View error: '.$e->getMessage(), previous: $e);
        }
    }

    private function accessToken(): string
    {
        return Cache::remember('ups:oauth_token', now()->addMinutes(50), function () {
            $response = Http::asForm()
                ->withBasicAuth($this->clientId, $this->clientSecret)
                ->post(rtrim($this->baseUrl, '/').'/security/v1/oauth/token', ['grant_type' => 'client_credentials']);

            if ($response->failed()) {
                throw new TrackingException("UPS OAuth token request failed ({$response->status()}).");
            }

            return (string) $response->json('access_token');
        });
    }

    /** @return QuantumViewActivity[] */
    private function parse(array $body): array
    {
        $activities = [];

        $events = data_get($body, 'QuantumViewResponse.QuantumViewEvents.SubscriptionEvents', []);
        foreach ((array) $events as $event) {
            foreach ((array) data_get($event, 'SubscriptionFile', []) as $file) {
                foreach ((array) data_get($file, 'Manifest', []) as $manifest) {
                    $scheduled = $this->date(data_get($manifest, 'Shipment.ScheduledDeliveryDate') ?? data_get($manifest, 'ScheduledDeliveryDate'));
                    $recipient = data_get($manifest, 'Shipment.ShipTo.Name') ?? data_get($manifest, 'ShipTo.Name');
                    foreach ((array) data_get($manifest, 'Package', data_get($manifest, 'Shipment.Package', [])) as $pkg) {
                        if ($tn = data_get($pkg, 'TrackingNumber')) {
                            $activities[] = new QuantumViewActivity(
                                trackingNumber: $tn,
                                type: 'manifest',
                                scheduledDelivery: $scheduled,
                                occurredAt: Carbon::now(),
                                description: 'Shipment manifest received by UPS',
                                location: null,
                                recipientName: $recipient,
                                references: $this->references($pkg),
                            );
                        }
                    }
                }

                foreach (['Origin' => 'origin', 'Delivery' => 'delivery', 'Exception' => 'exception'] as $key => $type) {
                    foreach ((array) data_get($file, $key, []) as $act) {
                        if ($tn = data_get($act, 'TrackingNumber')) {
                            $activities[] = new QuantumViewActivity(
                                trackingNumber: $tn,
                                type: $type,
                                scheduledDelivery: null,
                                occurredAt: $this->dateTime(data_get($act, 'Date'), data_get($act, 'Time')),
                                description: data_get($act, 'ActivityType') ?? ucfirst($type),
                                location: $this->location($act),
                                recipientName: null,
                                references: $this->references($act),
                            );
                        }
                    }
                }
            }
        }

        return $activities;
    }

    private function references(array $node): array
    {
        $refs = data_get($node, 'Reference', data_get($node, 'ReferenceNumber', []));
        $refs = isset($refs['Value']) || isset($refs['Number']) ? [$refs] : (array) $refs;

        return collect($refs)
            ->map(fn ($r) => is_array($r) ? (data_get($r, 'Value') ?? data_get($r, 'Number')) : $r)
            ->filter()->values()->all();
    }

    private function location(array $act): ?string
    {
        $parts = array_filter([
            data_get($act, 'ActivityLocation.City') ?? data_get($act, 'Address.City'),
            data_get($act, 'ActivityLocation.StateProvinceCode') ?? data_get($act, 'Address.StateProvinceCode'),
        ]);

        return $parts ? implode(', ', $parts) : null;
    }

    private function date(?string $date): ?Carbon
    {
        return $date ? (Carbon::createFromFormat('Ymd', $date) ?: null)?->startOfDay() : null;
    }

    private function dateTime(?string $date, ?string $time): ?Carbon
    {
        if (! $date) {
            return null;
        }

        return Carbon::createFromFormat('YmdHis', $date.str_pad($time ?? '000000', 6, '0')) ?: null;
    }
}
