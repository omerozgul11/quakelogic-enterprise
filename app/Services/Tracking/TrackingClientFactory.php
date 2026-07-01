<?php

namespace App\Services\Tracking;

use App\Enums\Carrier;
use App\Services\Dhl\DhlShipmentMapper;
use App\Services\Dhl\FakeDhlTrackingClient;
use App\Services\Dhl\RealDhlTrackingClient;
use App\Services\JbHunt\FakeJbHuntTrackingClient;
use App\Services\JbHunt\RealJbHuntTrackingClient;
use App\Services\Rl\FakeRlCarriersTrackingClient;
use App\Services\Rl\RealRlCarriersTrackingClient;
use App\Services\Ups\FakeUpsTrackingClient;
use App\Services\Ups\RealUpsTrackingClient;

/**
 * Resolves the right tracking client for a carrier. This is the ONE place to
 * register a new carrier — add a match arm returning its client (e.g.
 * Carrier::Fedex => new FedexTrackingClient(...)). Live carriers use the real
 * client only when enabled + credentialed; otherwise the fake drives dev + tests.
 */
class TrackingClientFactory
{
    public function for(Carrier|string $carrier): CarrierTrackingClient
    {
        $enum = $carrier instanceof Carrier ? $carrier : Carrier::tryFrom($carrier);

        // Custom/unknown carriers (e.g. a freight company entered by hand) and the
        // not-yet-integrated enum carriers all get the unsupported client, which
        // fails loudly rather than silently treating them as a live carrier.
        return match (true) {
            $enum === Carrier::Ups => $this->ups(),
            $enum === Carrier::JbHunt => $this->jbHunt(),
            $enum === Carrier::RlCarriers => $this->rlCarriers(),
            $enum === Carrier::Dhl => $this->dhl(),
            $enum !== null => new UnsupportedCarrierClient($enum->label()),
            default => new UnsupportedCarrierClient(
                is_string($carrier) && trim($carrier) !== '' ? trim($carrier) : 'This carrier'
            ),
        };
    }

    private function ups(): CarrierTrackingClient
    {
        $ups = config('services.ups');

        if ($ups['sync_enabled'] && $ups['client_id'] && $ups['client_secret']) {
            return new RealUpsTrackingClient($ups['client_id'], $ups['client_secret'], $ups['base_url']);
        }

        return new FakeUpsTrackingClient();
    }

    private function jbHunt(): CarrierTrackingClient
    {
        $jbh = config('services.jbhunt');

        if ($jbh['sync_enabled'] && $jbh['client_id'] && $jbh['client_secret']) {
            return new RealJbHuntTrackingClient(
                $jbh['client_id'],
                $jbh['client_secret'],
                $jbh['base_url'],
                $jbh['token_url'] ?? null,
                $jbh['scope'] ?? null,
                $jbh['subscription_key'] ?? null,
                $jbh['track_path'],
            );
        }

        // No live credentials: a deterministic simulator keeps the pipeline
        // exercisable in automated tests, but on a real deployment we never
        // fabricate freight data — the shipment stays manual (status + documents
        // by hand) until credentials are added, at which point the poll picks it
        // up automatically. Gated on runningUnitTests() (not the environment name)
        // so a server mistakenly running APP_ENV=local can't invent a "delivered"
        // status for a live shipment.
        return app()->runningUnitTests()
            ? new FakeJbHuntTrackingClient()
            : new UnsupportedCarrierClient('J.B. Hunt');
    }

    private function rlCarriers(): CarrierTrackingClient
    {
        // R+L's public tracing page is reachable by plain HTTP (no reCAPTCHA), so
        // the real client works WITHOUT credentials — it scrapes the page and, when
        // an API key is present, uses R+L's documented REST API instead. The fake
        // only drives the test suite.
        if (app()->runningUnitTests()) {
            return new FakeRlCarriersTrackingClient();
        }

        $rl = config('services.rlcarriers');

        return new RealRlCarriersTrackingClient(
            $rl['api_key'] ?? null,
            $rl['api_base_url'] ?? 'https://api.rlc.com',
            $rl['web_base_url'] ?? 'https://www.rlcarriers.com',
        );
    }

    private function dhl(): CarrierTrackingClient
    {
        $dhl = config('services.dhl');

        // The DHL-API-Key powers both the pull tracking API (this client) and the
        // push subscriptions. With a key, manual refresh + polling work; live
        // updates additionally arrive via the webhook (DhlPushIngestService).
        if (! empty($dhl['api_key'])) {
            return new RealDhlTrackingClient($dhl['api_key'], $dhl['base_url'], new DhlShipmentMapper());
        }

        // No key: like J.B. Hunt, DHL has no production simulator — it stays push-
        // driven/manual rather than fabricating data. The fake only drives tests.
        return app()->runningUnitTests()
            ? new FakeDhlTrackingClient()
            : new UnsupportedCarrierClient('DHL');
    }
}
