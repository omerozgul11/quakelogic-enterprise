<?php

namespace Tests\Feature\Opportunities;

use App\Models\Opportunity;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpportunityTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;
    private User $admin;
    private User $readOnly;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesPermissionsSeeder::class);
        $this->organization = Organization::factory()->create();
        $this->admin = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->admin->assignRole('Super Admin');
        $this->readOnly = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->readOnly->assignRole('Read Only');
    }

    public function test_admin_can_view_opportunities_list(): void
    {
        $response = $this->actingAs($this->admin)->get('/opportunities');
        $response->assertStatus(200);
    }

    public function test_admin_can_create_opportunity(): void
    {
        $response = $this->actingAs($this->admin)->post('/opportunities', [
            'title' => 'Test Government Contract',
            'source' => 'manual',
            'status' => 'new',
            'agency_name' => 'Department of Defense',
            'estimated_value' => 500000,
            'due_date' => now()->addDays(30)->format('Y-m-d'),
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('opportunities', [
            'title' => 'Test Government Contract',
            'organization_id' => $this->organization->id,
        ]);
    }

    public function test_read_only_cannot_create_opportunity(): void
    {
        $response = $this->actingAs($this->readOnly)->post('/opportunities', [
            'title' => 'Test',
            'source' => 'manual',
            'status' => 'new',
        ]);

        $response->assertStatus(403);
    }

    public function test_opportunity_view_is_organization_scoped(): void
    {
        $otherOrg = Organization::factory()->create();
        $otherUser = User::factory()->create(['organization_id' => $otherOrg->id]);
        $otherUser->assignRole('Super Admin');

        $opportunity = Opportunity::factory()->create(['organization_id' => $this->organization->id]);

        // Other org user cannot view this opportunity
        $response = $this->actingAs($otherUser)->get("/opportunities/{$opportunity->id}");
        $response->assertStatus(403);
    }

    public function test_admin_can_delete_opportunity(): void
    {
        $opportunity = Opportunity::factory()->create(['organization_id' => $this->organization->id]);

        $response = $this->actingAs($this->admin)->delete("/opportunities/{$opportunity->id}");
        $response->assertRedirect();
        $this->assertSoftDeleted('opportunities', ['id' => $opportunity->id]);
    }

    public function test_opportunity_requires_title(): void
    {
        $response = $this->actingAs($this->admin)->post('/opportunities', [
            'source' => 'manual',
            'status' => 'new',
        ]);

        $response->assertSessionHasErrors('title');
    }
}
