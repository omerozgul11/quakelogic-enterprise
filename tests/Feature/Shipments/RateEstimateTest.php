<?php

namespace Tests\Feature\Shipments;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RateEstimateTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesPermissionsSeeder::class);
        $org = Organization::factory()->create();
        $this->user = User::factory()->create(['organization_id' => $org->id]);
        $this->user->givePermissionTo('access shipments');
    }

    public function test_endpoint_returns_a_card_based_estimate(): void
    {
        $res = $this->actingAs($this->user)->postJson('/shipments/rates/estimate', [
            'origin_country' => 'US',
            'dest_country' => 'GB',
            'weight' => 5,
            'weight_unit' => 'lb',
            'content_type' => 'package',
            'discount_pct' => 0.40,
        ])->assertOk();

        $res->assertJsonPath('estimate.zone', 'C')
            ->assertJsonPath('estimate.band', 'standard')
            ->assertJsonPath('estimate.service_level', 'DHL EXPRESS WORLDWIDE');
        $this->assertEqualsWithDelta(262.54, $res->json('estimate.published_amount'), 0.001);
        $this->assertEqualsWithDelta(157.52, $res->json('estimate.net_amount'), 0.001);
    }

    public function test_premium_add_on_is_applied(): void
    {
        $res = $this->actingAs($this->user)->postJson('/shipments/rates/estimate', [
            'dest_country' => 'GB', 'weight' => 5, 'discount_pct' => 0.60, 'premium' => '9',
        ])->assertOk();

        $this->assertEqualsWithDelta(130.22, $res->json('estimate.net_amount'), 0.001);
    }

    public function test_unknown_country_returns_422_with_message(): void
    {
        $this->actingAs($this->user)
            ->postJson('/shipments/rates/estimate', ['dest_country' => 'ZZ', 'weight' => 5])
            ->assertStatus(422)
            ->assertJsonStructure(['message']);
    }

    public function test_create_form_exposes_the_rate_card(): void
    {
        $this->actingAs($this->user)->get('/shipments/rates/create')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Shipments/Rates/Create')
                ->where('rateCard.available', true)
                ->has('rateCard.discount_tiers'));
    }

    public function test_estimate_requires_shipments_access(): void
    {
        $org = Organization::factory()->create();
        $stranger = User::factory()->create(['organization_id' => $org->id]);

        $this->actingAs($stranger)
            ->postJson('/shipments/rates/estimate', ['dest_country' => 'GB', 'weight' => 5])
            ->assertForbidden();
    }
}
