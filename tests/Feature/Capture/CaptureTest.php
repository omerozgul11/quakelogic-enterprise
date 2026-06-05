<?php

namespace Tests\Feature\Capture;

use App\Models\CapturePlan;
use App\Models\Opportunity;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CaptureTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;
    private User $captureManager;
    private User $readOnly;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesPermissionsSeeder::class);
        $this->organization = Organization::factory()->create();

        $this->captureManager = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->captureManager->assignRole('Capture Manager');

        $this->readOnly = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->readOnly->assignRole('Read Only');
    }

    public function test_capture_manager_can_create_capture_plan(): void
    {
        $opportunity = Opportunity::factory()->create(['organization_id' => $this->organization->id]);

        $response = $this->actingAs($this->captureManager)->post('/capture', [
            'opportunity_id' => $opportunity->id,
            'stage' => 'discovery',
            'win_probability' => 35,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('capture_plans', [
            'opportunity_id' => $opportunity->id,
            'organization_id' => $this->organization->id,
        ]);
    }

    public function test_capture_stage_can_transition_forward(): void
    {
        $opportunity = Opportunity::factory()->create(['organization_id' => $this->organization->id]);
        $capturePlan = CapturePlan::factory()->create([
            'opportunity_id' => $opportunity->id,
            'organization_id' => $this->organization->id,
            'stage' => 'discovery',
        ]);

        $response = $this->actingAs($this->captureManager)->post("/capture/{$capturePlan->id}/transition", [
            'stage' => 'qualification',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('capture_plans', [
            'id' => $capturePlan->id,
            'stage' => 'qualification',
        ]);
    }

    public function test_capture_cannot_skip_stages(): void
    {
        $opportunity = Opportunity::factory()->create(['organization_id' => $this->organization->id]);
        $capturePlan = CapturePlan::factory()->create([
            'opportunity_id' => $opportunity->id,
            'organization_id' => $this->organization->id,
            'stage' => 'discovery',
        ]);

        // Trying to jump from discovery to submission should fail
        $response = $this->actingAs($this->captureManager)->post("/capture/{$capturePlan->id}/transition", [
            'stage' => 'submission',
        ]);

        $response->assertSessionHasErrors();
        $this->assertDatabaseHas('capture_plans', ['id' => $capturePlan->id, 'stage' => 'discovery']);
    }

    public function test_duplicate_capture_plan_per_opportunity_is_rejected(): void
    {
        $opportunity = Opportunity::factory()->create(['organization_id' => $this->organization->id]);
        CapturePlan::factory()->create(['opportunity_id' => $opportunity->id, 'organization_id' => $this->organization->id]);

        $response = $this->actingAs($this->captureManager)->post('/capture', [
            'opportunity_id' => $opportunity->id,
            'stage' => 'discovery',
        ]);

        $response->assertSessionHasErrors();
    }
}
