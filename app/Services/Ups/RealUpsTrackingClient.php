<?php

namespace App\Services\Ups;

use App\Enums\MailingStatus;
use App\Services\Tracking\CarrierTrackingClient;
use App\Services\Tracking\TrackingEvent;
use App\Services\Tracking\TrackingException;
use App\Services\Tracking\TrackingResult;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Live UPS Tracking API client (OAuth2 client-credentials). Used when
 * UPS_SYNC_ENABLED=true and credentials are present (see TrackingClientFactory).
 */
class RealUpsTrackingClient implements CarrierTrackingClient
{
    public function __construct(
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $baseUrl,
    ) {}

    public function track(string $trackingNumber): TrackingResult
    {
        try {
            $response = Http::withToken($this->accessToken())
                ->withHeaders(['transId' => (string) Str::uuid(), 'transactionSrc' => 'quakelogic-shipments'])
                ->acceptJson()
                ->get(rtrim($this->baseUrl, '/')."/api/track/v1/details/{$trackingNumber}", [
                    'locale' => 'en_US',
                    'returnSignature' => 'false',
                ]);

            if ($response->failed()) {
                throw new TrackingException("UPS tracking request failed ({$response->status()}) for {$trackingNumber}.");
            }

            return $this->parse($response->json());
        } catch (TrackingException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new TrackingException("UPS tracking error for {$trackingNumber}: {$e->getMessage()}", previous: $e);
        }
    }

    private function accessToken(): string
    {
        return Cache::remember('ups:oauth_token', now()->addMinutes(50), function () {
            $response = Http::asForm()
                ->withBasicAuth($this->clientId, $this->clientSecret)
                ->post(rtrim($this->baseUrl, '/').'/security/v1/oauth/token', [
                    'grant_type' => 'client_credentials',
                ]);

            if ($response->failed()) {
                throw new TrackingException("UPS OAuth token request failed ({$response->status()}).");
            }

            return (string) $response->json('access_token');
        });
    }

    private function parse(array $body): TrackingResult
    {
        $package = data_get($body, 'trackResponse.shipment.0.package.0', []);
        $code = data_get($package, 'currentStatus.code');
        $desc = data_get($package, 'currentStatus.description');

        $events = [];
        foreach (data_get($package, 'activity', []) as $activity) {
            $events[] = new TrackingEvent(
                data_get($activity, 'status.code'),
                (string) data_get($activity, 'status.description', 'Update'),
                $this->location($activity),
                $this->timestamp(data_get($activity, 'date'), data_get($activity, 'time')),
            );
        }

        $status = MailingStatus::fromUpsCode($code);
        $scheduled = $this->date(data_get($package, 'deliveryDate.0.date'));
        $deliveredAt = null;
        $receivedBy = null;
        if ($status === MailingStatus::Delivered) {
            $deliveredAt = $events[0]->occurredAt ?? Carbon::now();
            $receivedBy = data_get($package, 'deliveryInformation.receivedBy');
        }

        return new TrackingResult($status, $code, $desc, $scheduled, $deliveredAt, $receivedBy, null, $events);
    }

    private function location(array $activity): ?string
    {
        $parts = array_filter([
            data_get($activity, 'location.address.city'),
            data_get($activity, 'location.address.stateProvince'),
            data_get($activity, 'location.address.country'),
        ]);

        return $parts ? implode(', ', $parts) : null;
    }

    private function timestamp(?string $date, ?string $time): Carbon
    {
        if (! $date) {
            return Carbon::now();
        }

        return Carbon::createFromFormat('YmdHis', $date.str_pad($time ?? '000000', 6, '0'))
            ?: Carbon::now();
    }

    private function date(?string $date): ?Carbon
    {
        return $date ? Carbon::createFromFormat('Ymd', $date)?->startOfDay() : null;
    }
}
