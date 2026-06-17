<?php

namespace Tests\Feature\Dashboard;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExchangeRatesTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $org = Organization::factory()->create();
        // fresh() so the in-memory instance carries every column (incl. the
        // nullable notification_preferences) for strict-mode attribute access.
        $this->user = User::factory()->create(['organization_id' => $org->id])->fresh();
    }

    public function test_dashboard_includes_daily_exchange_rates_and_threshold(): void
    {
        // EXCHANGE_RATES_ENABLED is false in tests, so it uses the reference feed
        // (no network) — the dashboard still gets rates and the default threshold.
        $this->actingAs($this->user)
            ->get('/')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard/Index')
                ->where('exchangeRates.source', 'reference')
                ->has('exchangeRates.rates', 6)
                ->where('exchangeRates.rates.0.code', 'EUR')
                ->where('eurUsdThreshold', 1.14)
            );
    }

    public function test_user_can_set_the_eur_usd_threshold(): void
    {
        $this->actingAs($this->user)
            ->put('/settings/preferences', [
                'display' => ['theme' => 'system', 'density' => 'comfortable'],
                'dashboard' => ['default_view' => 'personal', 'eur_usd_threshold' => 1.2],
                'channels' => ['new_proposal' => true, 'new_opportunity' => true, 'desktop' => true, 'sound' => true],
            ])
            ->assertRedirect();

        $this->assertEquals(1.2, $this->user->fresh()->notification_preferences['dashboard']['eur_usd_threshold']);
    }
}
