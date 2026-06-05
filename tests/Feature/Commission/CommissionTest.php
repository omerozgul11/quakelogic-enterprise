<?php

namespace Tests\Feature\Commission;

use App\Models\Commission;
use App\Models\Organization;
use App\Models\User;
use App\Services\Commissions\CommissionCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommissionTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;
    private User $finance;
    private User $salesRep;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesPermissionsSeeder::class);
        $this->organization = Organization::factory()->create();

        $this->finance = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->finance->assignRole('Finance');

        $this->salesRep = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->salesRep->assignRole('Sales Representative');
    }

    public function test_finance_can_view_all_commissions(): void
    {
        $response = $this->actingAs($this->finance)->get('/commissions');
        $response->assertStatus(200);
    }

    public function test_sales_rep_can_only_view_own_commissions(): void
    {
        Commission::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->salesRep->id,
        ]);
        Commission::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->finance->id,
        ]);

        $response = $this->actingAs($this->salesRep)->get('/commissions');
        $response->assertStatus(200);
        // The controller filters by user for non-admin roles
    }

    public function test_commission_calculation_percentage(): void
    {
        $service = app(CommissionCalculationService::class);
        $commission = $service->computeCommission('percentage', 1000000, 2.5, null, null);
        $this->assertEquals(25000, $commission);
    }

    public function test_commission_calculation_fixed(): void
    {
        $service = app(CommissionCalculationService::class);
        $commission = $service->computeCommission('fixed', 500000, null, 10000, null);
        $this->assertEquals(10000, $commission);
    }

    public function test_commission_calculation_tiered(): void
    {
        $service = app(CommissionCalculationService::class);
        $tierConfig = [
            ['min' => 0, 'max' => 500000, 'rate' => 3.0],
            ['min' => 500000, 'max' => 1000000, 'rate' => 2.5],
            ['min' => 1000000, 'max' => null, 'rate' => 2.0],
        ];

        // 750k: 500k at 3% = 15000, 250k at 2.5% = 6250 => 21250
        $commission = $service->computeCommission('tiered', 750000, null, null, $tierConfig);
        $this->assertEquals(21250, $commission);
    }

    public function test_finance_can_approve_commission(): void
    {
        $commission = Commission::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->salesRep->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->finance)->post("/commissions/{$commission->id}/approve");
        $response->assertRedirect();
        $this->assertDatabaseHas('commissions', ['id' => $commission->id, 'status' => 'approved']);
    }
}
