<?php

namespace Tests\Feature\Shipments;

use App\Enums\MailingStatus;
use App\Models\DhlPushSubscription;
use App\Models\Organization;
use App\Models\ProposalMailing;
use App\Models\User;
use App\Notifications\MailingStatusChanged;
use App\Services\Dhl\DhlPushClient;
use App\Services\Dhl\DhlPushIngestService;
use App\Services\Dhl\DhlShipmentMapper;
use App\Services\Dhl\FakeDhlPushClient;
use App\Services\Dhl\FakeDhlTrackingClient;
use App\Services\Dhl\RealDhlTrackingClient;
use App\Services\Tracking\TrackingClientFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DhlPushTrackingTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesPermissionsSeeder::class);
        $this->org = Organization::factory()->create();
        $this->user = User::factory()->create(['organization_id' => $this->org->id]);
        $this->user->givePermissionTo('access shipments');
    }

    // ---- Webhook endpoint -------------------------------------------------

    public function test_webhook_rejects_an_invalid_token(): void
    {
        config()->set('services.dhl.push.webhook_token', 'right-token');

        $this->postJson('/api/dhl/webhook/wrong-token', ['scope' => 'subscription.push'])
            ->assertStatus(401);
    }

    public function test_webhook_processes_a_push_end_to_end_and_updates_the_shipment(): void
    {
        Notification::fake();
        config()->set('services.dhl.push.webhook_token', 'tok');

        $mailing = $this->dhlMailing('JD0123', MailingStatus::InTransit);

        $this->postJson('/api/dhl/webhook/tok', [
            'scope' => 'subscription.push',
            'self' => 'https://api-eu.dhl.com/tracking/push/v1/subscription/none',
            'shipments' => [[
                'id' => 'JD0123',
                'status' => [
                    'timestamp' => '2026-06-30T10:00:00Z',
                    'statusCode' => 'delivered',
                    'status' => 'Delivered',
                    'description' => 'Delivered to recipient',
                ],
            ]],
        ])->assertOk();

        $this->assertSame(MailingStatus::Delivered, $mailing->fresh()->status);
    }

    // ---- Subscription lifecycle ------------------------------------------

    public function test_validate_event_activates_the_subscription(): void
    {
        $fake = new FakeDhlPushClient();
        $this->app->instance(DhlPushClient::class, $fake);

        $sub = DhlPushSubscription::create([
            'organization_id' => $this->org->id,
            'subscription_id' => 'sub-123',
            'type' => DhlPushSubscription::TYPE_SHIPMENT,
            'tracking_number' => 'JD9',
            'status' => DhlPushSubscription::STATUS_PENDING,
            'created_by' => $this->user->id,
        ]);

        app(DhlPushIngestService::class)->handle([
            'scope' => 'subscription.validate',
            'self' => 'https://api-eu.dhl.com/tracking/push/v1/subscription/sub-123',
            'secret' => 'shhh',
        ]);

        $this->assertSame([['id' => 'sub-123', 'secret' => 'shhh']], $fake->activated);
        $this->assertSame(DhlPushSubscription::STATUS_VALIDATING, $sub->fresh()->status);
    }

    public function test_ready_event_marks_the_subscription_live(): void
    {
        $sub = DhlPushSubscription::create([
            'organization_id' => $this->org->id,
            'subscription_id' => 'sub-r',
            'type' => DhlPushSubscription::TYPE_SHIPMENT,
            'status' => DhlPushSubscription::STATUS_VALIDATING,
            'created_by' => $this->user->id,
        ]);

        app(DhlPushIngestService::class)->handle([
            'scope' => 'subscription.ready',
            'self' => 'https://api-eu.dhl.com/tracking/push/v1/subscription/sub-r',
        ]);

        $this->assertSame(DhlPushSubscription::STATUS_READY, $sub->fresh()->status);
    }

    // ---- Push ingest ------------------------------------------------------

    public function test_push_updates_shipment_records_events_and_notifies(): void
    {
        Notification::fake();
        $mailing = $this->dhlMailing('JD777', MailingStatus::InTransit);

        app(DhlPushIngestService::class)->handle([
            'scope' => 'subscription.push',
            'self' => 'https://api-eu.dhl.com/tracking/push/v1/subscription/none',
            'shipments' => [[
                'id' => 'JD777',
                'status' => [
                    'timestamp' => '2026-06-30T09:00:00Z',
                    'statusCode' => 'delivered',
                    'status' => 'Delivered',
                    'location' => ['address' => ['addressLocality' => 'AMSTERDAM', 'countryCode' => 'NL']],
                ],
            ]],
        ]);

        $mailing->refresh();
        $this->assertSame(MailingStatus::Delivered, $mailing->status);
        $this->assertNotNull($mailing->delivered_at);
        $this->assertDatabaseHas('mailing_tracking_events', [
            'proposal_mailing_id' => $mailing->id,
            'code' => 'D',
        ]);
        Notification::assertSentTo($this->user, MailingStatusChanged::class);
    }

    public function test_push_for_an_unknown_shipment_does_not_fabricate_a_mailing(): void
    {
        // No subscription context → we can't attribute the shipment to an org, so
        // nothing is created (real DHL data only; never fabricated).
        app(DhlPushIngestService::class)->handle([
            'scope' => 'subscription.push',
            'self' => 'https://api-eu.dhl.com/tracking/push/v1/subscription/ghost',
            'shipments' => [[
                'id' => 'UNKNOWN-1',
                'status' => ['statusCode' => 'transit', 'status' => 'In transit'],
            ]],
        ]);

        $this->assertSame(0, ProposalMailing::count());
    }

    public function test_account_subscription_push_creates_the_shipment(): void
    {
        DhlPushSubscription::create([
            'organization_id' => $this->org->id,
            'subscription_id' => 'acct-1',
            'type' => DhlPushSubscription::TYPE_ACCOUNT,
            'account_number' => '123456789',
            'status' => DhlPushSubscription::STATUS_READY,
            'created_by' => $this->user->id,
        ]);

        app(DhlPushIngestService::class)->handle([
            'scope' => 'subscription.push',
            'self' => 'https://api-eu.dhl.com/tracking/push/v1/subscription/acct-1',
            'shipments' => [[
                'id' => 'ACCT-SHIP-9',
                'status' => ['statusCode' => 'transit', 'status' => 'Processed'],
            ]],
        ]);

        $this->assertDatabaseHas('proposal_mailings', [
            'organization_id' => $this->org->id,
            'ups_tracking_number' => 'ACCT-SHIP-9',
            'carrier' => 'dhl',
            'created_by' => $this->user->id,
        ]);
    }

    // ---- Mapper -----------------------------------------------------------

    public function test_mapper_derives_status_from_dhl_status_codes(): void
    {
        $mapper = new DhlShipmentMapper();

        $this->assertSame(MailingStatus::Delivered, $mapper->toResult([
            'id' => 'a', 'status' => ['statusCode' => 'delivered', 'status' => 'Delivered'],
        ])->status);

        // The "out for delivery" state only lives in DHL's free text.
        $this->assertSame(MailingStatus::OutForDelivery, $mapper->toResult([
            'id' => 'b', 'status' => ['statusCode' => 'transit', 'status' => 'Out for delivery'],
        ])->status);

        // "in customs" is a transit sub-state → in transit, not out for delivery.
        $this->assertSame(MailingStatus::InTransit, $mapper->toResult([
            'id' => 'c', 'status' => ['statusCode' => 'transit', 'status' => 'in customs'],
        ])->status);

        $this->assertSame(MailingStatus::LabelCreated, $mapper->toResult([
            'id' => 'd', 'status' => ['statusCode' => 'pre-transit', 'status' => 'Shipment information received'],
        ])->status);
    }

    // ---- Carrier wiring ---------------------------------------------------

    public function test_factory_resolves_the_dhl_client_by_credentials(): void
    {
        config()->set('services.dhl.api_key', 'dhl-key');
        config()->set('services.dhl.base_url', 'https://api-eu.dhl.com');
        $this->assertInstanceOf(RealDhlTrackingClient::class, app(TrackingClientFactory::class)->for('dhl'));

        config()->set('services.dhl.api_key', null);
        $this->assertInstanceOf(FakeDhlTrackingClient::class, app(TrackingClientFactory::class)->for('dhl'));
    }

    public function test_carriers_page_shows_dhl_as_live_when_configured(): void
    {
        config()->set('services.dhl.api_key', 'dhl-key');
        config()->set('services.dhl.push.webhook_token', 'tok');

        $this->actingAs($this->user)->get('/shipments/carriers')->assertInertia(
            fn (Assert $page) => $page
                ->component('Shipments/Carriers')
                ->where('dhl.apiConfigured', true)
                ->where('dhl.pushConfigured', true)
                ->where('carriers', fn ($carriers) => collect($carriers)->firstWhere('key', 'dhl')['status'] === 'live')
        );
    }

    private function dhlMailing(string $tracking, MailingStatus $status): ProposalMailing
    {
        return ProposalMailing::create([
            'organization_id' => $this->org->id,
            'created_by' => $this->user->id,
            'ups_tracking_number' => $tracking,
            'carrier' => 'dhl',
            'status' => $status->value,
            'auto_track' => true,
        ]);
    }
}
