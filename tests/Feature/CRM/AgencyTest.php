<?php

namespace Tests\Feature\CRM;

use App\Models\Agency;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgencyTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;
    private User $bdm;
    private User $readOnly;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesPermissionsSeeder::class);
        $this->organization = Organization::factory()->create();

        $this->bdm = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->bdm->assignRole('Business Development Manager');

        $this->readOnly = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->readOnly->assignRole('Read Only');
    }

    public function test_bdm_can_view_agencies(): void
    {
        $response = $this->actingAs($this->bdm)->get('/agencies');
        $response->assertStatus(200);
    }

    public function test_bdm_can_create_agency(): void
    {
        $response = $this->actingAs($this->bdm)->post('/agencies', [
            'name' => 'Department of Energy',
            'acronym' => 'DOE',
            'agency_type' => 'federal',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('agencies', [
            'name' => 'Department of Energy',
            'organization_id' => $this->organization->id,
        ]);
    }

    public function test_read_only_can_view_but_not_create_agencies(): void
    {
        $this->actingAs($this->readOnly)->get('/agencies')->assertStatus(200);

        $response = $this->actingAs($this->readOnly)->post('/agencies', [
            'name' => 'Test Agency',
        ]);
        $response->assertStatus(403);
    }

    public function test_agency_is_organization_scoped(): void
    {
        $otherOrg = Organization::factory()->create();
        $otherUser = User::factory()->create(['organization_id' => $otherOrg->id]);
        $otherUser->assignRole('Business Development Manager');

        $agency = Agency::factory()->create(['organization_id' => $this->organization->id]);

        $response = $this->actingAs($otherUser)->get("/agencies/{$agency->id}");
        $response->assertStatus(403);
    }
}
