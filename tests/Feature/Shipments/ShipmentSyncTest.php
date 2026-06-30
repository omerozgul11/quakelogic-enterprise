<?php

namespace Tests\Feature\Shipments;

use App\Models\Organization;
use App\Models\ProposalMailing;
use App\Models\User;
use App\Services\Ups\QuantumView\FakeQuantumViewClient;
use App\Services\Ups\QuantumView\QuantumViewClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShipmentSyncTest extends TestCase
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

    public function test_sync_button_route_is_reachable_and_refreshes(): void
    {
        ProposalMailing::create([
            'organization_id' => $this->org->id, 'created_by' => $this->user->id,
            'ups_tracking_number' => '1ZSEED0001', 'carrier' => 'ups', 'status' => 'in_transit', 'auto_track' => true,
        ]);

        $this->actingAs($this->user)->post('/shipments/sync')->assertRedirect();
    }

    public function test_sync_does_not_fabricate_shipments_when_quantum_view_is_off(): void
    {
        // Quantum View is off by default → the simulator must NOT run, so no
        // random shipments are invented. Only the existing one may be refreshed.
        ProposalMailing::create([
            'organization_id' => $this->org->id, 'created_by' => $this->user->id,
            'ups_tracking_number' => '1ZSEED0002', 'carrier' => 'ups', 'status' => 'in_transit', 'auto_track' => true,
        ]);

        $this->actingAs($this->user)->post('/shipments/sync')->assertRedirect();

        $this->assertSame(1, ProposalMailing::forOrganization($this->org->id)->count());
    }

    public function test_sync_pulls_new_labels_when_quantum_view_is_configured(): void
    {
        config()->set('services.ups.client_id', 'cid');
        config()->set('services.ups.client_secret', 'secret');
        config()->set('services.ups.quantum_view.enabled', true);
        config()->set('services.ups.quantum_view.subscription', 'sub');
        // Ingest is account-level; point it at this test org explicitly.
        config()->set('services.ups.quantum_view.organization_id', $this->org->id);
        // Bind the simulator explicitly so no real UPS call is made.
        $this->app->instance(QuantumViewClient::class, new FakeQuantumViewClient());

        $this->assertSame(0, ProposalMailing::forOrganization($this->org->id)->count());

        $this->actingAs($this->user)->post('/shipments/sync')->assertRedirect();

        // The fake returns a manifest + a delivery activity → two new shipments pulled.
        $this->assertSame(2, ProposalMailing::forOrganization($this->org->id)->count());
    }

    public function test_roleless_user_cannot_sync(): void
    {
        $stranger = User::factory()->create(['organization_id' => $this->org->id]);
        $this->actingAs($stranger)->post('/shipments/sync')->assertForbidden();
    }
}
