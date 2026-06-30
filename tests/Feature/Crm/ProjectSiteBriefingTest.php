<?php

namespace Tests\Feature\Crm;

use App\Enums\ProjectStatus;
use App\Models\Crm\Project;
use App\Models\Crm\ProjectContact;
use App\Models\Crm\ProjectSite;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 1 of the Project Field Information System: the per-project Site & Safety
 * briefing — installation sites (access/security/utilities/hazards/emergency)
 * and typed stakeholder contacts. Purely additive to the existing Projects app.
 */
class ProjectSiteBriefingTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $admin;      // Super Admin — manage all projects
    private User $lead;       // Sales Rep — owner/lead of the project
    private User $outsider;   // Read Only — unassigned

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
            'name' => 'Field Install — Seismic Array',
            'project_number' => 'QL-PROJ-TEST-'.random_int(1000, 9999),
            'status' => ProjectStatus::New->value,
            'created_via' => 'manual',
        ]);
    }

    /** A lead can add a site; the first site becomes primary; tri-state booleans persist. */
    public function test_lead_can_add_a_site_and_first_is_primary(): void
    {
        $project = $this->project();

        $this->actingAs($this->lead)->post("/projects/{$project->id}/sites", [
            'name' => 'Main Plant — Building C',
            'address' => "123 Industrial Way\nReno, NV 89501",
            'badge_required' => true,
            'forklift_available' => true,
            'power_available' => null,
            'high_voltage' => true,
            'nearest_hospital' => 'Renown Regional',
            'hospital_phone' => '775-555-0100',
        ])->assertRedirect();

        $site = ProjectSite::where('crm_project_id', $project->id)->firstOrFail();
        $this->assertTrue($site->is_primary, 'First site is primary.');
        $this->assertTrue($site->badge_required);
        $this->assertTrue($site->forklift_available);
        $this->assertNull($site->power_available, 'Unknown tri-state stays null.');
        $this->assertTrue($site->high_voltage);
        $this->assertSame('Renown Regional', $site->nearest_hospital);
        $this->assertTrue($project->activities()->where('action', 'site_added')->exists());
    }

    /** A second site is not primary; promoting it demotes the first. */
    public function test_make_primary_demotes_the_others(): void
    {
        $project = $this->project();

        $this->actingAs($this->lead)->post("/projects/{$project->id}/sites", ['name' => 'Site A']);
        $this->actingAs($this->lead)->post("/projects/{$project->id}/sites", ['name' => 'Site B']);

        $a = ProjectSite::where('crm_project_id', $project->id)->where('name', 'Site A')->firstOrFail();
        $b = ProjectSite::where('crm_project_id', $project->id)->where('name', 'Site B')->firstOrFail();
        $this->assertTrue($a->is_primary);
        $this->assertFalse($b->is_primary);

        $this->actingAs($this->lead)->put("/projects/{$project->id}/sites/{$b->id}", [
            'name' => 'Site B', 'is_primary' => true,
        ])->assertRedirect();

        $this->assertTrue($b->fresh()->is_primary);
        $this->assertFalse($a->fresh()->is_primary, 'Old primary was demoted.');
    }

    /** Deleting the primary site promotes another remaining site. */
    public function test_deleting_primary_promotes_next(): void
    {
        $project = $this->project();
        $this->actingAs($this->lead)->post("/projects/{$project->id}/sites", ['name' => 'Site A']);
        $this->actingAs($this->lead)->post("/projects/{$project->id}/sites", ['name' => 'Site B']);
        $a = ProjectSite::where('crm_project_id', $project->id)->where('name', 'Site A')->firstOrFail();

        $this->actingAs($this->lead)->delete("/projects/{$project->id}/sites/{$a->id}")->assertRedirect();

        $b = ProjectSite::where('crm_project_id', $project->id)->where('name', 'Site B')->firstOrFail();
        $this->assertTrue($b->is_primary, 'Remaining site is promoted to primary.');
    }

    /** A typed, emergency contact can be added and is categorised. */
    public function test_lead_can_add_a_typed_contact(): void
    {
        $project = $this->project();

        $this->actingAs($this->lead)->post("/projects/{$project->id}/contacts", [
            'category' => 'procurement',
            'name' => 'Dana Okafor',
            'title' => 'Procurement Officer',
            'phone' => '775-555-0150',
            'preferred_contact_method' => 'email',
            'is_emergency' => false,
        ])->assertRedirect();

        $contact = ProjectContact::where('crm_project_id', $project->id)->firstOrFail();
        $this->assertSame(\App\Enums\Crm\ProjectContactCategory::Procurement, $contact->category);
        $this->assertSame('Dana Okafor', $contact->name);
        $this->assertFalse($contact->is_emergency);
        $this->assertTrue($project->activities()->where('action', 'contact_added')->exists());
    }

    /** Site name is required. */
    public function test_site_name_is_required(): void
    {
        $project = $this->project();

        $this->actingAs($this->lead)
            ->post("/projects/{$project->id}/sites", ['name' => ''])
            ->assertSessionHasErrors('name');
    }

    /** Sites & contacts are serialised into the project detail payload. */
    public function test_sites_and_contacts_appear_in_show_payload(): void
    {
        $project = $this->project();
        $this->actingAs($this->lead)->post("/projects/{$project->id}/sites", ['name' => 'Site A']);
        $this->actingAs($this->lead)->post("/projects/{$project->id}/contacts", ['category' => 'security', 'name' => 'Guard Desk']);

        $this->actingAs($this->admin)->get("/projects/{$project->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('sites', 1)
                ->has('siteContacts', 1)
                ->has('contactCategories'));
    }

    /** An unassigned, read-only user cannot add a site. */
    public function test_outsider_cannot_add_a_site(): void
    {
        $project = $this->project();

        $this->actingAs($this->outsider)
            ->post("/projects/{$project->id}/sites", ['name' => 'Sneaky Site'])
            ->assertForbidden();

        $this->assertSame(0, ProjectSite::where('crm_project_id', $project->id)->count());
    }

    /** A site from another project can't be mutated through this project's URL. */
    public function test_cannot_touch_a_site_from_another_project(): void
    {
        $project = $this->project();
        $other = $this->project();
        $this->actingAs($this->lead)->post("/projects/{$other->id}/sites", ['name' => 'Other site']);
        $otherSite = ProjectSite::where('crm_project_id', $other->id)->firstOrFail();

        $this->actingAs($this->lead)
            ->delete("/projects/{$project->id}/sites/{$otherSite->id}")
            ->assertNotFound();
    }
}
