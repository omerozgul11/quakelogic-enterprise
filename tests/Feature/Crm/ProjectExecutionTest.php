<?php

namespace Tests\Feature\Crm;

use App\Enums\Crm\ExecutionRecordType;
use App\Enums\ProjectStatus;
use App\Models\Crm\Project;
use App\Models\Crm\ProjectChecklist;
use App\Models\Crm\ProjectChecklistItem;
use App\Models\Crm\ProjectExecutionRecord;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 3 of the Project Field Information System: execution records and
 * tick-off checklists. Additive to the existing Projects app.
 */
class ProjectExecutionTest extends TestCase
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

    /** A lead can add an execution record; type + performer persist. */
    public function test_lead_can_add_execution_record(): void
    {
        $project = $this->project();

        $this->actingAs($this->lead)->post("/projects/{$project->id}/execution-records", [
            'type' => 'commissioning',
            'title' => 'Site commissioning',
            'status' => 'in_progress',
            'scheduled_date' => '2026-08-01',
            'performed_by' => $this->lead->id,
            'summary' => 'Energise and verify all channels.',
        ])->assertRedirect();

        $record = ProjectExecutionRecord::where('crm_project_id', $project->id)->firstOrFail();
        $this->assertSame(ExecutionRecordType::Commissioning, $record->type);
        $this->assertSame('in_progress', $record->status->value);
        $this->assertSame($this->lead->id, $record->performed_by);
        $this->assertTrue($project->activities()->where('action', 'execution_record_added')->exists());
    }

    /** Status defaults to scheduled when omitted. */
    public function test_record_status_defaults_to_scheduled(): void
    {
        $project = $this->project();
        $this->actingAs($this->lead)->post("/projects/{$project->id}/execution-records", [
            'type' => 'installation', 'title' => 'Rack install',
        ])->assertRedirect();
        $this->assertSame('scheduled', ProjectExecutionRecord::where('crm_project_id', $project->id)->firstOrFail()->status->value);
    }

    /** An invalid type is rejected. */
    public function test_invalid_type_is_rejected(): void
    {
        $project = $this->project();
        $this->actingAs($this->lead)->post("/projects/{$project->id}/execution-records", [
            'type' => 'teleportation', 'title' => 'X',
        ])->assertSessionHasErrors('type');
    }

    /** A checklist accumulates items and ticking one records who/when. */
    public function test_checklist_items_and_toggle(): void
    {
        $project = $this->project();
        $this->actingAs($this->lead)->post("/projects/{$project->id}/checklists", ['title' => 'Pre-departure'])->assertRedirect();
        $list = ProjectChecklist::where('crm_project_id', $project->id)->firstOrFail();

        $this->actingAs($this->lead)->post("/projects/{$project->id}/checklists/{$list->id}/items", ['text' => 'Pack calibrated torque wrench'])->assertRedirect();
        $item = ProjectChecklistItem::where('crm_project_checklist_id', $list->id)->firstOrFail();
        $this->assertFalse($item->is_done);

        $this->actingAs($this->lead)->patch("/projects/{$project->id}/checklists/{$list->id}/items/{$item->id}", ['is_done' => true])->assertRedirect();
        $item->refresh();
        $this->assertTrue($item->is_done);
        $this->assertSame($this->lead->id, $item->done_by);
        $this->assertNotNull($item->done_at);

        // Un-ticking clears the metadata.
        $this->actingAs($this->lead)->patch("/projects/{$project->id}/checklists/{$list->id}/items/{$item->id}", ['is_done' => false])->assertRedirect();
        $item->refresh();
        $this->assertFalse($item->is_done);
        $this->assertNull($item->done_by);
        $this->assertNull($item->done_at);
    }

    /** Items can't be added to a checklist on a different project. */
    public function test_cannot_add_item_to_foreign_checklist(): void
    {
        $project = $this->project();
        $other = $this->project();
        $this->actingAs($this->lead)->post("/projects/{$other->id}/checklists", ['title' => 'Other list']);
        $foreign = ProjectChecklist::where('crm_project_id', $other->id)->firstOrFail();

        $this->actingAs($this->lead)
            ->post("/projects/{$project->id}/checklists/{$foreign->id}/items", ['text' => 'Sneaky'])
            ->assertNotFound();
    }

    /** Execution data + options are serialised into the show payload. */
    public function test_payload_exposes_execution(): void
    {
        $project = $this->project();
        $this->actingAs($this->lead)->post("/projects/{$project->id}/checklists", ['title' => 'Punch list']);

        $this->actingAs($this->admin)->get("/projects/{$project->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('executionRecords')
                ->has('checklists', 1)
                ->where('checklists.0.total_count', 0)
                ->has('executionTypes')
                ->has('executionStatuses'));
    }

    /** An unassigned read-only user cannot add a record. */
    public function test_outsider_cannot_add_record(): void
    {
        $project = $this->project();
        $this->actingAs($this->outsider)
            ->post("/projects/{$project->id}/execution-records", ['type' => 'installation', 'title' => 'X'])
            ->assertForbidden();
        $this->assertSame(0, ProjectExecutionRecord::where('crm_project_id', $project->id)->count());
    }
}
