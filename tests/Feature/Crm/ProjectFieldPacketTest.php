<?php

namespace Tests\Feature\Crm;

use App\Enums\ProjectStatus;
use App\Models\Crm\Project;
use App\Models\Crm\ProjectChecklist;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 7 of the Project Field Information System: the AI field briefing and the
 * printable Field Packet (PDF). The packet test populates one of every entity so
 * every blade section renders end-to-end through dompdf.
 */
class ProjectFieldPacketTest extends TestCase
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

    /** A lead can generate the AI briefing; it is stored and attributed. */
    public function test_lead_can_generate_briefing(): void
    {
        $project = $this->project();

        $this->actingAs($this->lead)->post("/projects/{$project->id}/briefing")->assertRedirect();

        $project->refresh();
        $this->assertNotEmpty($project->ai_briefing);
        $this->assertSame($this->lead->id, $project->ai_briefing_by);
        $this->assertNotNull($project->ai_briefing_generated_at);
        $this->assertTrue($project->activities()->where('action', 'briefing_generated')->exists());
    }

    /** The Field Packet renders a PDF across every populated section. */
    public function test_field_packet_downloads_a_pdf(): void
    {
        $project = $this->project();
        $this->actingAs($this->lead);

        $this->post("/projects/{$project->id}/sites", ['name' => 'Main Plant', 'badge_required' => true, 'hazards' => 'Pinch points', 'nearest_hospital' => 'Renown']);
        $this->post("/projects/{$project->id}/contacts", ['category' => 'security', 'name' => 'Guard Desk', 'is_emergency' => true]);
        $this->post("/projects/{$project->id}/equipment", ['name' => 'Seismometer', 'model' => 'TS-200', 'serial_number' => 'SN1', 'weight' => '240 lbs', 'installation_location' => 'Pad A']);
        $this->post("/projects/{$project->id}/shipments", ['carrier' => 'ups', 'tracking_number' => '1Z999', 'shock_indicator' => 'tripped']);
        $this->post("/projects/{$project->id}/execution-records", ['type' => 'installation', 'title' => 'Rack install']);
        $this->post("/projects/{$project->id}/checklists", ['title' => 'Pre-departure']);
        $list = ProjectChecklist::where('crm_project_id', $project->id)->firstOrFail();
        $this->post("/projects/{$project->id}/checklists/{$list->id}/items", ['text' => 'Pack torque wrench']);
        $this->post("/projects/{$project->id}/travel", ['type' => 'flight', 'title' => 'UA123', 'from_location' => 'SFO', 'to_location' => 'RNO']);
        $this->post("/projects/{$project->id}/briefing");

        $response = $this->get("/projects/{$project->id}/field-packet");

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    /** An empty project still produces a valid packet (no blade crash on missing sections). */
    public function test_field_packet_works_for_empty_project(): void
    {
        $project = $this->project();
        $response = $this->actingAs($this->lead)->get("/projects/{$project->id}/field-packet");
        $response->assertOk();
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    /** An unassigned read-only user can neither generate nor download. */
    public function test_outsider_is_blocked(): void
    {
        $project = $this->project();
        $this->actingAs($this->outsider)->post("/projects/{$project->id}/briefing")->assertForbidden();
        $this->actingAs($this->outsider)->get("/projects/{$project->id}/field-packet")->assertForbidden();
    }
}
