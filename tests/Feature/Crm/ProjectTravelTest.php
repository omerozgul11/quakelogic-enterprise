<?php

namespace Tests\Feature\Crm;

use App\Enums\Crm\TravelType;
use App\Enums\ProjectStatus;
use App\Models\Crm\Project;
use App\Models\Crm\ProjectTravel;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 4 of the Project Field Information System: travel arrangements.
 */
class ProjectTravelTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $admin;
    private User $lead;
    private User $outsider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesPermissionsSeeder::class);
        $this->org = Organization::factory()->create();
        $this->admin = $this->user('Super Admin');
        $this->lead = $this->user('Sales Representative');
        $this->outsider = $this->user('Read Only');
    }

    private function user(string $role): User
    {
        $user = User::factory()->create(['organization_id' => $this->org->id, 'is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    private function project(): Project
    {
        return Project::create([
            'organization_id' => $this->org->id,
            'created_by' => $this->admin->id,
            'owner_id' => $this->lead->id,
            'project_manager_id' => $this->lead->id,
            'name' => 'Field Install',
            'project_number' => 'QL-PROJ-TEST-'.random_int(1000, 9999),
            'status' => ProjectStatus::New->value,
            'created_via' => 'manual',
        ]);
    }

    /** A lead can add a flight with traveler, schedule and cost. */
    public function test_lead_can_add_travel(): void
    {
        $project = $this->project();

        $this->actingAs($this->lead)->post("/projects/{$project->id}/travel", [
            'type' => 'flight',
            'title' => 'United 1234 SFO→RNO',
            'status' => 'booked',
            'traveler_id' => $this->lead->id,
            'provider' => 'United',
            'confirmation_number' => 'ABC123',
            'start_at' => '2026-08-01T09:30',
            'from_location' => 'SFO',
            'to_location' => 'RNO',
            'cost' => 450.50,
            'currency' => 'USD',
        ])->assertRedirect();

        $travel = ProjectTravel::where('crm_project_id', $project->id)->firstOrFail();
        $this->assertSame(TravelType::Flight, $travel->type);
        $this->assertSame($this->lead->id, $travel->traveler_id);
        $this->assertSame('450.50', $travel->cost);
        $this->assertNotNull($travel->start_at);
        $this->assertTrue($project->activities()->where('action', 'travel_added')->exists());
    }

    /** Free-text traveler name is kept when no user is chosen. */
    public function test_traveler_name_is_kept(): void
    {
        $project = $this->project();
        $this->actingAs($this->lead)->post("/projects/{$project->id}/travel", [
            'type' => 'lodging', 'title' => 'Hilton Reno', 'traveler_name' => 'Guest Engineer',
        ])->assertRedirect();

        $travel = ProjectTravel::where('crm_project_id', $project->id)->firstOrFail();
        $this->assertNull($travel->traveler_id);
        $this->assertSame('Guest Engineer', $travel->traveler_name);
    }

    /** Invalid type and status are rejected. */
    public function test_invalid_type_and_status_rejected(): void
    {
        $project = $this->project();
        $this->actingAs($this->lead)->post("/projects/{$project->id}/travel", ['type' => 'rocket', 'title' => 'X'])
            ->assertSessionHasErrors('type');
        $this->actingAs($this->lead)->post("/projects/{$project->id}/travel", ['type' => 'flight', 'title' => 'X', 'status' => 'maybe'])
            ->assertSessionHasErrors('status');
    }

    /** A bad booking URL is rejected. */
    public function test_invalid_booking_url_rejected(): void
    {
        $project = $this->project();
        $this->actingAs($this->lead)->post("/projects/{$project->id}/travel", [
            'type' => 'flight', 'title' => 'X', 'booking_url' => 'not a url',
        ])->assertSessionHasErrors('booking_url');
    }

    /** Travel data + type options are serialised into the show payload. */
    public function test_payload_exposes_travel(): void
    {
        $project = $this->project();
        $this->actingAs($this->lead)->post("/projects/{$project->id}/travel", ['type' => 'car_rental', 'title' => 'Hertz', 'cost' => 200.50]);

        $this->actingAs($this->admin)->get("/projects/{$project->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('travel', 1)
                ->where('travel.0.type_label', 'Car rental')
                ->where('travel.0.cost', 200.5)
                ->has('travelTypes'));
    }

    /** An unassigned read-only user cannot add travel. */
    public function test_outsider_cannot_add_travel(): void
    {
        $project = $this->project();
        $this->actingAs($this->outsider)
            ->post("/projects/{$project->id}/travel", ['type' => 'flight', 'title' => 'X'])
            ->assertForbidden();
        $this->assertSame(0, ProjectTravel::where('crm_project_id', $project->id)->count());
    }

    /** Travel from another project can't be mutated through this project's URL. */
    public function test_cannot_touch_foreign_travel(): void
    {
        $project = $this->project();
        $other = $this->project();
        $this->actingAs($this->lead)->post("/projects/{$other->id}/travel", ['type' => 'flight', 'title' => 'Other']);
        $foreign = ProjectTravel::where('crm_project_id', $other->id)->firstOrFail();

        $this->actingAs($this->lead)->delete("/projects/{$project->id}/travel/{$foreign->id}")->assertNotFound();
    }
}
