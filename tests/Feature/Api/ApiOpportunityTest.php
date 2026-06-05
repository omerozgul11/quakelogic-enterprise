<?php

namespace Tests\Feature\Api;

use App\Models\Opportunity;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiOpportunityTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;
    private User $user;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesPermissionsSeeder::class);
        $this->organization = Organization::factory()->create();
        $this->user = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->user->assignRole('Business Development Manager');
        $this->token = $this->user->createToken('api-test')->plainTextToken;
    }

    public function test_api_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/opportunities');
        $response->assertStatus(401);
    }

    public function test_authenticated_api_returns_opportunities(): void
    {
        Opportunity::factory(5)->create(['organization_id' => $this->organization->id]);

        $response = $this->withToken($this->token)->getJson('/api/v1/opportunities');

        $response->assertStatus(200);
        $response->assertJsonCount(5, 'data');
    }

    public function test_api_filters_by_organization(): void
    {
        $otherOrg = Organization::factory()->create();
        Opportunity::factory(3)->create(['organization_id' => $this->organization->id]);
        Opportunity::factory(2)->create(['organization_id' => $otherOrg->id]);

        $response = $this->withToken($this->token)->getJson('/api/v1/opportunities');

        $response->assertStatus(200);
        $response->assertJsonCount(3, 'data');
    }

    public function test_api_health_endpoint(): void
    {
        $response = $this->withToken($this->token)->getJson('/api/v1/health');
        $response->assertStatus(200);
        $response->assertJsonStructure(['status', 'version', 'timestamp']);
    }

    public function test_api_me_returns_current_user(): void
    {
        $response = $this->withToken($this->token)->getJson('/api/v1/me');
        $response->assertStatus(200);
        $response->assertJsonPath('email', $this->user->email);
    }
}
