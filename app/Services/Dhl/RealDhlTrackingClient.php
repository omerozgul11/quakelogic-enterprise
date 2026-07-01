<?php

namespace App\Services\Dhl;

use App\Enums\MailingStatus;
use App\Services\Tracking\CarrierTrackingClient;
use App\Services\Tracking\TrackingException;
use App\Services\Tracking\TrackingResult;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Live DHL tracking via the "Shipment Tracking - Unified" request/response API
 * (api-eu.dhl.com/track/shipments), authenticated with the DHL-API-Key header.
 * Bound only when DHL_API_KEY is set (see TrackingClientFactory) — the same key
 * also powers the push subscriptions, so one credential lights up both the manual
 * "Refresh" (this client) and the live webhook updates (DhlPushIngestService).
 */
class RealDhlTrackingClient implements CarrierTrackingClient
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $baseUrl,
        private readonly DhlShipmentMapper $mapper,
    ) {}

    public function track(string $trackingNumber): TrackingResult
    {
        try {
            $response = Http::withHeaders(['DHL-API-Key' => $this->apiKey])
                ->acceptJson()
                ->connectTimeout(5)
                ->timeout(12)
                ->get(rtrim($this->baseUrl, '/').'/track/shipments', [
                    'trackingNumber' => $trackingNumber,
                ]);

            if ($response->failed()) {
                // Auth/config failures and gateway outages must surface so the poll
                // retries and misconfiguration is visible. A 404 (not scanned yet)
                // just means there's no data — record it as pending, like UPS/JBH.
                if (in_array($response->status(), [401, 403], true) || $response->serverError()) {
                    throw new TrackingException("DHL tracking request failed ({$response->status()}) for {$trackingNumber}.");
                }

                return $this->pending();
            }

            $shipment = data_get($response->json(), 'shipments.0');

            return is_array($shipment) ? $this->mapper->toResult($shipment) : $this->pending();
        } catch (TrackingException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new TrackingException("DHL tracking error for {$trackingNumber}: {$e->getMessage()}", previous: $e);
        }
    }

    private function pending(): TrackingResult
    {
        return new TrackingResult(
            MailingStatus::LabelCreated,
            null,
            'Awaiting first DHL scan.',
            null, null, null, null, [],
        );
    }
}
