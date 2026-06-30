<?php

namespace Tests\Feature\Shipments;

use App\Models\Organization;
use App\Models\ProposalMailing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CarrierProfileTest extends TestCase
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

    private function profiles(): array
    {
        return $this->org->fresh()->settings['carrier_profiles'] ?? [];
    }

    public function test_carriers_page_renders_with_login_defaults(): void
    {
        $this->actingAs($this->user)->get('/shipments/carriers')->assertOk();
    }

    public function test_saves_import_export_and_login_for_a_builtin_carrier(): void
    {
        $this->actingAs($this->user)->post('/shipments/carriers/profile', [
            'key' => 'dhl',
            'import_number' => 'IMP-12345',
            'export_number' => 'EXP-67890',
            'login_url' => 'mydhl.example.com',   // bare domain → https:// added
        ])->assertRedirect();

        $profile = $this->profiles()['dhl'] ?? [];
        $this->assertSame('IMP-12345', $profile['import_number']);
        $this->assertSame('EXP-67890', $profile['export_number']);
        $this->assertSame('https://mydhl.example.com', $profile['login_url']);
    }

    public function test_blank_profile_is_not_stored(): void
    {
        $this->actingAs($this->user)->post('/shipments/carriers/profile', [
            'key' => 'ups', 'import_number' => '', 'export_number' => '', 'login_url' => '',
        ])->assertRedirect();

        $this->assertArrayNotHasKey('ups', $this->profiles());
    }

    public function test_unknown_carrier_key_is_rejected(): void
    {
        $this->actingAs($this->user)->post('/shipments/carriers/profile', [
            'key' => 'totally bogus carrier', 'import_number' => 'x',
        ])->assertNotFound();
    }

    public function test_custom_carrier_can_be_renamed_and_keeps_its_profile(): void
    {
        $this->actingAs($this->user)->post('/shipments/carriers', ['name' => 'Old Freight'])->assertRedirect();
        $this->actingAs($this->user)->post('/shipments/carriers/profile', [
            'key' => 'Old Freight', 'import_number' => 'IM-1',
        ])->assertRedirect();

        $this->actingAs($this->user)->post('/shipments/carriers/profile', [
            'key' => 'Old Freight', 'new_name' => 'New Freight', 'import_number' => 'IM-2',
        ])->assertRedirect();

        $org = $this->org->fresh();
        $this->assertContains('New Freight', $org->settings['custom_carriers']);
        $this->assertNotContains('Old Freight', $org->settings['custom_carriers']);
        $this->assertSame('IM-2', $org->settings['carrier_profiles']['new freight']['import_number']);
        $this->assertArrayNotHasKey('old freight', $org->settings['carrier_profiles']);
    }

    public function test_removing_a_custom_carrier_forgets_its_profile(): void
    {
        $this->actingAs($this->user)->post('/shipments/carriers', ['name' => 'Acme Freight'])->assertRedirect();
        $this->actingAs($this->user)->post('/shipments/carriers/profile', [
            'key' => 'Acme Freight', 'import_number' => 'IM-9',
        ])->assertRedirect();
        $this->assertArrayHasKey('acme freight', $this->profiles());

        $this->actingAs($this->user)->post('/shipments/carriers/remove', ['key' => 'Acme Freight'])->assertRedirect();

        $this->assertArrayNotHasKey('acme freight', $this->profiles());
    }

    public function test_removing_a_builtin_carrier_hides_it_and_restore_brings_it_back(): void
    {
        $this->actingAs($this->user)->post('/shipments/carriers/remove', ['key' => 'fedex'])->assertRedirect();
        $this->assertContains('fedex', $this->org->fresh()->settings['hidden_carriers'] ?? []);

        // A hidden carrier disappears from the new-shipment carrier picker.
        $this->actingAs($this->user)->get('/shipments/mailings/create')->assertInertia(
            fn (Assert $p) => $p->where('carrierOptions', fn ($opts) => collect($opts)->doesntContain(fn ($o) => $o['value'] === 'fedex'))
        );

        $this->actingAs($this->user)->post('/shipments/carriers/restore', ['key' => 'fedex'])->assertRedirect();
        $this->assertNotContains('fedex', $this->org->fresh()->settings['hidden_carriers'] ?? []);
    }

    public function test_cannot_remove_a_carrier_that_has_shipments(): void
    {
        ProposalMailing::create([
            'organization_id' => $this->org->id,
            'created_by' => $this->user->id,
            'ups_tracking_number' => '1Z'.uniqid(),
            'carrier' => 'ups',
            'status' => 'in_transit',
        ]);

        $this->actingAs($this->user)->post('/shipments/carriers/remove', ['key' => 'ups'])->assertRedirect();

        $this->assertNotContains('ups', $this->org->fresh()->settings['hidden_carriers'] ?? []);
    }

    public function test_roleless_user_cannot_save_carrier_details(): void
    {
        $stranger = User::factory()->create(['organization_id' => $this->org->id]);
        $this->actingAs($stranger)->post('/shipments/carriers/profile', [
            'key' => 'ups', 'import_number' => 'x',
        ])->assertForbidden();
    }
}
