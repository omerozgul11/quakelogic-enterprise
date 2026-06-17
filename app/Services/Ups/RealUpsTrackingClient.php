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
                ->connectTimeout(5)
                ->timeout(12)
                ->get(rtrim($this->baseUrl, '/')."/api/track/v1/details/{$trackingNumber}", [
                    'locale' => 'en_US',
                    'returnSignature' => 'false',
                ]);

            if ($response->failed()) {
                // Auth problems and UPS-side outages are real failures: surface
                // them so the caller retries later and any misconfiguration is
                // visible. Everything else (a not-yet-scanned label, an unknown
                // or malformed number) means UPS simply has no usable data yet —
                // record the shipment as pending instead of failing the whole
                // batch; the poll/refresh fills it in once UPS ingests it.
                if (in_array($response->status(), [401, 403], true) || $response->serverError()) {
                    throw new TrackingException("UPS tracking request failed ({$response->status()}) for {$trackingNumber}.");
                }

                return new TrackingResult(
                    MailingStatus::LabelCreated,
                    null,
                    $this->errorMessage($response) ?? 'Awaiting first UPS scan.',
                    null, null, null, null, [],
                );
            }

            return $this->parse($response->json());
        } catch (TrackingException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new TrackingException("UPS tracking error for {$trackingNumber}: {$e->getMessage()}", previous: $e);
        }
    }

    /** Pull the human-readable error text out of a UPS error response, if any. */
    private function errorMessage($response): ?string
    {
        $msg = data_get($response->json(), 'response.errors.0.message')
            ?? data_get($response->json(), 'trackResponse.shipment.0.warnings.0.message');

        return $msg ? (string) $msg : null;
    }

    private function accessToken(): string
    {
        return Cache::remember('ups:oauth_token', now()->addMinutes(50), function () {
            $response = Http::asForm()
                ->connectTimeout(5)
                ->timeout(12)
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
        $activities = data_get($package, 'activity', []);

        $events = [];
        foreach ($activities as $activity) {
            $events[] = new TrackingEvent(
                data_get($activity, 'status.code'),
                (string) data_get($activity, 'status.description', 'Update'),
                $this->location($activity),
                $this->timestamp(data_get($activity, 'date'), data_get($activity, 'time')),
            );
        }

        $status = $this->resolveStatus($package, $activities);
        $code = data_get($package, 'currentStatus.code');
        $desc = (string) (data_get($activities, '0.status.description') ?: data_get($package, 'currentStatus.description'));
        $scheduled = $this->date(data_get($package, 'deliveryDate.0.date'));

        $deliveredAt = null;
        $receivedBy = null;
        if ($status === MailingStatus::Delivered) {
            $deliveredAt = $events[0]->occurredAt ?? Carbon::now();
            $receivedBy = data_get($package, 'deliveryInformation.receivedBy');
        }

        return new TrackingResult($status, $code, $desc, $scheduled, $deliveredAt, $receivedBy, null, $events);
    }

    /**
     * Determine the package's current status. The UPS Tracking API's
     * currentStatus.code is a granular numeric code (e.g. "003") that doesn't map
     * cleanly to a delivery state, so we read the high-level activity TYPE letters
     * (M=label, I=in transit, O=out for delivery, D=delivered, X=exception,
     * RS=returned) and fall back to description keywords. Delivered/returned are
     * terminal — if any scan reports one, it wins.
     *
     * @param  array<int, array<string, mixed>>  $activities
     */
    private function resolveStatus(array $package, array $activities): MailingStatus
    {
        foreach ($activities as $a) {
            $type = strtoupper((string) data_get($a, 'status.type'));
            $desc = strtolower((string) data_get($a, 'status.description'));
            if ($type === 'D' || str_contains($desc, 'delivered')) {
                return MailingStatus::Delivered;
            }
            if (in_array($type, ['RS', 'RT'], true) || str_contains($desc, 'returned to sender')) {
                return MailingStatus::Returned;
            }
        }

        $latest = $activities[0] ?? null;
        $byType = match (strtoupper((string) data_get($latest, 'status.type'))) {
            'M' => MailingStatus::LabelCreated,
            'I', 'P' => MailingStatus::InTransit,
            'O' => MailingStatus::OutForDelivery,
            'X' => MailingStatus::Exception,
            default => null,
        };
        if ($byType) {
            return $byType;
        }

        $desc = strtolower((string) (data_get($latest, 'status.description') ?: data_get($package, 'currentStatus.description')));

        return match (true) {
            str_contains($desc, 'out for delivery') => MailingStatus::OutForDelivery,
            str_contains($desc, 'exception') => MailingStatus::Exception,
            str_contains($desc, 'label') || str_contains($desc, 'ready for ups') || str_contains($desc, 'not yet received') || str_contains($desc, 'order processed') => MailingStatus::LabelCreated,
            default => MailingStatus::InTransit,
        };
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
