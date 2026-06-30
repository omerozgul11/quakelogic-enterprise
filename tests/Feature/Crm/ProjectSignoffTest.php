<?php

namespace Tests\Feature\Crm;

use App\Enums\Crm\SignoffType;
use App\Enums\ProjectStatus;
use App\Models\Crm\Project;
use App\Models\Crm\ProjectSignoff;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 6 of the Project Field Information System: digital sign-offs with a
 * timestamp and an optional drawn signature image.
 */
class ProjectSignoffTest extends TestCase
{
    use RefreshDatabase;

    // 1x1 transparent PNG data URL — a minimal valid signature image.
    private const PNG = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

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

    /** A lead can record a sign-off with a signature; it's timestamped and attributed. */
    public function test_lead_can_record_signoff(): void
    {
        $project = $this->project();

        $this->actingAs($this->lead)->post("/projects/{$project->id}/signoffs", [
            'type' => 'acceptance',
            'signer_name' => 'Pat Customer',
            'signer_title' => 'Facilities Director',
            'statement' => 'I accept this installation as complete.',
            'signature_data' => self::PNG,
            'signed_at' => '2026-08-15T14:30',
        ])->assertRedirect();

        $signoff = ProjectSignoff::where('crm_project_id', $project->id)->firstOrFail();
        $this->assertSame(SignoffType::Acceptance, $signoff->type);
        $this->assertSame('Pat Customer', $signoff->signer_name);
        $this->assertStringStartsWith('data:image/png', $signoff->signature_data);
        $this->assertSame($this->lead->id, $signoff->captured_by);
        $this->assertNotNull($signoff->signed_at);
        $this->assertTrue($project->activities()->where('action', 'signoff_recorded')->exists());
    }

    /** signed_at defaults to now when omitted. */
    public function test_signed_at_defaults_to_now(): void
    {
        $project = $this->project();
        $this->actingAs($this->lead)->post("/projects/{$project->id}/signoffs", ['type' => 'qa', 'signer_name' => 'QA Lead'])->assertRedirect();
        $this->assertNotNull(ProjectSignoff::where('crm_project_id', $project->id)->firstOrFail()->signed_at);
    }

    /** A signature payload that isn't an image data URL is rejected. */
    public function test_invalid_signature_data_rejected(): void
    {
        $project = $this->project();
        $this->actingAs($this->lead)->post("/projects/{$project->id}/signoffs", [
            'type' => 'customer', 'signer_name' => 'X', 'signature_data' => 'not-an-image',
        ])->assertSessionHasErrors('signature_data');
    }

    /** An invalid sign-off type is rejected. */
    public function test_invalid_type_rejected(): void
    {
        $project = $this->project();
        $this->actingAs($this->lead)->post("/projects/{$project->id}/signoffs", ['type' => 'mayor', 'signer_name' => 'X'])
            ->assertSessionHasErrors('type');
    }

    /** Sign-offs appear in the payload and render into the Field Packet PDF. */
    public function test_payload_and_packet_include_signoff(): void
    {
        $project = $this->project();
        $this->actingAs($this->lead)->post("/projects/{$project->id}/signoffs", [
            'type' => 'customer', 'signer_name' => 'Pat Customer', 'signature_data' => self::PNG,
        ]);

        $this->actingAs($this->admin)->get("/projects/{$project->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('signoffs', 1)->has('signoffTypes'));

        $pdf = $this->actingAs($this->admin)->get("/projects/{$project->id}/field-packet");
        $pdf->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
    }

    /** A foreign execution record can't be linked. */
    public function test_cannot_link_foreign_execution_record(): void
    {
        $project = $this->project();
        $other = $this->project();
        $this->actingAs($this->lead)->post("/projects/{$other->id}/execution-records", ['type' => 'installation', 'title' => 'Other rec']);
        $foreign = \App\Models\Crm\ProjectExecutionRecord::where('crm_project_id', $other->id)->firstOrFail();

        $this->actingAs($this->lead)->post("/projects/{$project->id}/signoffs", [
            'type' => 'customer', 'signer_name' => 'X', 'crm_project_execution_record_id' => $foreign->id,
        ])->assertSessionHasErrors('crm_project_execution_record_id');
    }

    /** An unassigned read-only user cannot record a sign-off. */
    public function test_outsider_is_blocked(): void
    {
        $project = $this->project();
        $this->actingAs($this->outsider)->post("/projects/{$project->id}/signoffs", ['type' => 'customer', 'signer_name' => 'X'])
            ->assertForbidden();
        $this->assertSame(0, ProjectSignoff::where('crm_project_id', $project->id)->count());
    }
}
