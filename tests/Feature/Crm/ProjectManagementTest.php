<?php

namespace Tests\Feature\Crm;

use App\Enums\ProjectStatus;
use App\Enums\ProposalStatus;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Crm\Project;
use App\Models\Crm\ProjectActivity;
use App\Models\Crm\ProjectMember;
use App\Models\Crm\ProjectSetting;
use App\Models\Crm\Task;
use App\Models\Opportunity;
use App\Models\Organization;
use App\Models\ProposalSubmission;
use App\Models\User;
use App\Notifications\ActivityNotification;
use App\Services\Crm\ProjectCreationService;
use App\Services\Proposals\ProposalWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ProjectManagementTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $admin;     // Super Admin — `manage all projects`
    private User $owner;     // Sales Rep — `manage projects`, NOT `manage all`
    private User $manager;   // Sales Rep — used as a project manager (lead, not admin)
    private User $member;    // Read Only — assignment grants access without manage
    private User $outsider;  // Read Only — unassigned

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesPermissionsSeeder::class);
        $this->org = Organization::factory()->create();

        $this->admin = $this->user('Super Admin');
        $this->owner = $this->user('Sales Representative');
        $this->manager = $this->user('Sales Representative');
        $this->member = $this->user('Read Only');
        $this->outsider = $this->user('Read Only');
    }

    private function user(string $role): User
    {
        $user = User::factory()->create(['organization_id' => $this->org->id, 'is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    private function makeProposal(User $owner, string $status = 'submitted', float $value = 120000): ProposalSubmission
    {
        $company = Company::factory()->create(['organization_id' => $this->org->id, 'created_by' => $owner->id]);

        return ProposalSubmission::create([
            'organization_id' => $this->org->id,
            'created_by' => $owner->id,
            'owner_id' => $owner->id,
            'company_id' => $company->id,
            'proposal_number' => 'QL-2026-'.str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT).random_int(10, 99),
            'project_name' => 'Seismic Array Upgrade',
            'status' => $status,
            'proposal_value' => $value,
            'currency' => 'USD',
            'description' => 'Full delivery scope.',
        ]);
    }

    private function award(ProposalSubmission $proposal, ?User $actor = null): ProposalSubmission
    {
        return app(ProposalWorkflowService::class)
            ->transition($proposal, ProposalStatus::Awarded, $actor ?? $this->admin, force: true);
    }

    /** 1–3 + ownership: awarding a proposal auto-creates a linked, owned, numbered project. */
    public function test_awarding_a_proposal_auto_creates_a_linked_project(): void
    {
        $proposal = $this->makeProposal($this->owner, value: 250000);

        $this->award($proposal);

        $project = Project::where('proposal_submission_id', $proposal->id)->first();
        $this->assertNotNull($project, 'A project should be created on award.');
        $this->assertSame($this->owner->id, $project->owner_id, 'Owner is copied from the proposal owner.');
        $this->assertSame('automatic', $project->created_via);
        $this->assertSame(ProjectStatus::New, $project->status);
        $this->assertSame('250000.00', $project->budget);
        $this->assertStringStartsWith('QL-PROJ-', $project->project_number);
        $this->assertSame($proposal->company_id, $project->company_id);

        // Owner seeded onto the team + activity recorded.
        $this->assertTrue($project->members()->where('user_id', $this->owner->id)->exists());
        $this->assertTrue($project->activities()->where('action', 'created')->exists());
    }

    /** The award path still creates the Contract — existing behaviour isn't broken. */
    public function test_award_still_creates_the_contract_and_sets_status(): void
    {
        $proposal = $this->makeProposal($this->owner);

        $this->award($proposal);

        $this->assertSame(ProposalStatus::Awarded, $proposal->fresh()->status);
        $this->assertTrue(Contract::where('proposal_submission_id', $proposal->id)->exists());
    }

    /** Duplicate prevention: awarding twice yields exactly one project. */
    public function test_duplicate_project_is_not_created_for_the_same_proposal(): void
    {
        $proposal = $this->makeProposal($this->owner);
        $service = app(ProjectCreationService::class);

        $service->createFromProposal($proposal, $this->admin);
        $service->createFromProposal($proposal, $this->admin);
        // And again via the award handler.
        $service->handleProposalAwarded($proposal->fresh(), $this->admin);

        $this->assertSame(1, Project::where('proposal_submission_id', $proposal->id)->count());
    }

    /** Project numbers increment per organization. */
    public function test_project_numbers_are_sequential(): void
    {
        $first = app(ProjectCreationService::class)->createFromProposal($this->makeProposal($this->owner), $this->admin);
        $second = app(ProjectCreationService::class)->createFromProposal($this->makeProposal($this->owner), $this->admin);

        $year = now()->year;
        $this->assertSame("QL-PROJ-{$year}-001", $first->project_number);
        $this->assertSame("QL-PROJ-{$year}-002", $second->project_number);
    }

    /** Settings can switch off the automation entirely. */
    public function test_auto_creation_can_be_disabled_in_settings(): void
    {
        ProjectSetting::create(['organization_id' => $this->org->id, 'auto_create_on_award' => false]);
        $proposal = $this->makeProposal($this->owner);

        $this->award($proposal);

        $this->assertFalse(Project::where('proposal_submission_id', $proposal->id)->exists());
    }

    /** Manual creation from an awarded proposal via the controller. */
    public function test_admin_can_manually_create_a_project_from_a_proposal(): void
    {
        $proposal = $this->makeProposal($this->owner, status: 'awarded');

        $this->actingAs($this->admin)
            ->post('/crm/projects', ['proposal_submission_id' => $proposal->id])
            ->assertRedirect();

        $project = Project::where('proposal_submission_id', $proposal->id)->firstOrFail();
        $this->assertSame('manual', $project->created_via);
    }

    /** Admin can change the project owner and assign the project manager. */
    public function test_admin_can_change_owner_and_assign_manager(): void
    {
        $project = app(ProjectCreationService::class)->createFromProposal($this->makeProposal($this->owner), $this->admin);

        $this->actingAs($this->admin)->put("/crm/projects/{$project->id}", [
            'name' => $project->name,
            'owner_id' => $this->manager->id,
            'project_manager_id' => $this->manager->id,
        ])->assertRedirect();

        $project->refresh();
        $this->assertSame($this->manager->id, $project->owner_id);
        $this->assertSame($this->manager->id, $project->project_manager_id);
        $this->assertTrue($project->activities()->where('action', 'manager_assigned')->exists());
    }

    /** A non-admin lead may edit fields but NOT reassign ownership. */
    public function test_non_admin_cannot_change_owner(): void
    {
        $project = app(ProjectCreationService::class)->createFromProposal($this->makeProposal($this->owner), $this->admin);

        $this->actingAs($this->owner)->put("/crm/projects/{$project->id}", [
            'name' => 'Renamed by owner',
            'owner_id' => $this->member->id,
        ])->assertRedirect();

        $project->refresh();
        $this->assertSame('Renamed by owner', $project->name);
        $this->assertSame($this->owner->id, $project->owner_id, 'Owner must be unchanged for a non-admin.');
    }

    /** Admin can add and remove team members. */
    public function test_admin_can_add_and_remove_team_members(): void
    {
        $project = app(ProjectCreationService::class)->createFromProposal($this->makeProposal($this->owner), $this->admin);

        $this->actingAs($this->admin)->post("/crm/projects/{$project->id}/members", [
            'user_id' => $this->member->id, 'role' => 'engineer', 'responsibility' => 'Field install',
        ])->assertRedirect();
        $member = ProjectMember::where('crm_project_id', $project->id)->where('user_id', $this->member->id)->firstOrFail();
        $this->assertSame('engineer', $member->role);

        $this->actingAs($this->admin)->delete("/crm/projects/{$project->id}/members/{$member->id}")->assertRedirect();
        $this->assertFalse(ProjectMember::where('id', $member->id)->exists());
    }

    /** A project manager (lead, not admin) can manage tasks. */
    public function test_project_manager_can_manage_tasks(): void
    {
        $project = app(ProjectCreationService::class)->createFromProposal($this->makeProposal($this->owner), $this->admin);
        $project->update(['project_manager_id' => $this->manager->id]);

        $this->actingAs($this->manager)->post("/crm/projects/{$project->id}/tasks", [
            'title' => 'Mobilize crew', 'priority' => 'high',
        ])->assertRedirect();

        $this->assertTrue($project->tasks()->where('title', 'Mobilize crew')->exists());
    }

    /** A team member may update a task assigned to them, but not others. */
    public function test_team_member_can_update_only_their_own_task(): void
    {
        $project = app(ProjectCreationService::class)->createFromProposal($this->makeProposal($this->owner), $this->admin);
        ProjectMember::create(['organization_id' => $this->org->id, 'crm_project_id' => $project->id, 'user_id' => $this->member->id, 'role' => 'member', 'is_active' => true]);

        $mine = $this->makeTask($project, $this->member->id);
        $theirs = $this->makeTask($project, $this->owner->id);

        $this->actingAs($this->member)->put("/crm/projects/{$project->id}/tasks/{$mine->id}", ['status' => 'in_progress'])->assertRedirect();
        $this->assertSame('in_progress', $mine->fresh()->status->value);

        $this->actingAs($this->member)->put("/crm/projects/{$project->id}/tasks/{$theirs->id}", ['status' => 'completed'])->assertForbidden();
    }

    /** A regular user cannot see a project they're not assigned to; an assigned one can. */
    public function test_unassigned_user_cannot_view_project_but_assigned_can(): void
    {
        $project = app(ProjectCreationService::class)->createFromProposal($this->makeProposal($this->owner), $this->admin);

        $this->actingAs($this->outsider)->get("/crm/projects/{$project->id}")->assertForbidden();

        ProjectMember::create(['organization_id' => $this->org->id, 'crm_project_id' => $project->id, 'user_id' => $this->member->id, 'role' => 'member', 'is_active' => true]);
        $this->actingAs($this->member)->get("/crm/projects/{$project->id}")->assertOk();
    }

    /** The index is assignment-scoped for non-admins. */
    public function test_index_is_scoped_for_non_admins(): void
    {
        $mine = app(ProjectCreationService::class)->createFromProposal($this->makeProposal($this->member), $this->admin);
        $other = app(ProjectCreationService::class)->createFromProposal($this->makeProposal($this->owner), $this->admin);

        $this->actingAs($this->member)->get('/crm/projects')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('stats.total', 1)
                ->has('projects.data', 1));

        // Admin sees both.
        $this->actingAs($this->admin)->get('/crm/projects')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('stats.total', 2));
    }

    /** Status changes are recorded in the activity log. */
    public function test_status_change_is_recorded_in_activity_log(): void
    {
        $project = app(ProjectCreationService::class)->createFromProposal($this->makeProposal($this->owner), $this->admin);

        $this->actingAs($this->admin)->put("/crm/projects/{$project->id}", [
            'name' => $project->name, 'status' => ProjectStatus::InProgress->value,
        ])->assertRedirect();

        $this->assertTrue($project->activities()->where('action', 'status_changed')->exists());
    }

    /** Notifications fire on project creation. */
    public function test_notifications_are_sent_on_creation(): void
    {
        Notification::fake();
        $proposal = $this->makeProposal($this->owner);

        app(ProjectCreationService::class)->createFromProposal($proposal, $this->admin);

        Notification::assertSentTo($this->owner, ActivityNotification::class);
    }

    /** Awarding an opportunity (interactively) spins up a project. */
    public function test_awarding_an_opportunity_creates_a_project(): void
    {
        $opportunity = Opportunity::create([
            'organization_id' => $this->org->id,
            'created_by' => $this->owner->id,
            'owner_id' => $this->owner->id,
            'title' => 'City Sensor Network',
            'status' => 'submitted',
            'estimated_value' => 90000,
        ]);

        $this->actingAs($this->admin);
        $opportunity->update(['status' => 'awarded']);

        $project = Project::where('opportunity_id', $opportunity->id)->first();
        $this->assertNotNull($project);
        $this->assertSame($this->owner->id, $project->owner_id);
    }

    /** Existing CRM project creation (no proposal) still works. */
    public function test_plain_manual_project_creation_still_works(): void
    {
        $this->actingAs($this->owner)->post('/crm/projects', [
            'name' => 'Internal R&D', 'status' => ProjectStatus::Planning->value,
        ])->assertRedirect();

        $project = Project::where('name', 'Internal R&D')->firstOrFail();
        $this->assertSame($this->owner->id, $project->owner_id);
        $this->assertStringStartsWith('QL-PROJ-', $project->project_number);
        $this->assertSame('manual', $project->created_via);
    }

    private function makeTask(Project $project, ?int $assignedTo): Task
    {
        return $project->tasks()->create([
            'organization_id' => $this->org->id,
            'created_by' => $this->admin->id,
            'title' => 'Task '.random_int(1000, 9999),
            'status' => 'open',
            'priority' => 'medium',
            'assigned_to' => $assignedTo,
        ]);
    }
}
