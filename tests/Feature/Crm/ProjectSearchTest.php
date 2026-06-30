<?php

namespace Tests\Feature\Crm;

use App\Enums\ProjectStatus;
use App\Models\Crm\Project;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 8 (polish): the Projects index search reaches across a project's field
 * data — find it by an equipment serial, a shipment tracking number, a site or
 * a site contact, not only by name/number. Org-scoped throughout.
 */
class ProjectSearchTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $admin;
    private User $lead;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesPermissionsSeeder::class);
        $this->org = Organization::factory()->create();
        $this->admin = $this->user('Super Admin');
        $this->lead = $this->user('Sales Representative');
    }

    private function user(string $role): User
    {
        $u = User::factory()->create(['organization_id' => $this->org->id, 'is_active' => true]);
        $u->assignRole($role);

        return $u;
    }

    private function project(string $name): Project
    {
        return Project::create([
            'organization_id' => $this->org->id,
            'created_by' => $this->admin->id,
            'owner_id' => $this->lead->id,
            'project_manager_id' => $this->lead->id,
            'name' => $name,
            'project_number' => 'QL-PROJ-TEST-'.random_int(1000, 9999),
            'status' => ProjectStatus::New->value,
            'created_via' => 'manual',
        ]);
    }

    private function search(string $q): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->admin)->get('/projects?search='.urlencode($q));
    }

    private function ids(\Illuminate\Testing\TestResponse $r): array
    {
        $ids = [];
        $r->assertInertia(function ($page) use (&$ids) {
            $ids = collect($page->toArray()['props']['projects']['data'])->pluck('id')->all();
        });

        return $ids;
    }

    public function test_finds_project_by_equipment_serial_shipment_tracking_site_and_contact(): void
    {
        $target = $this->project('Seismic Array');
        $decoy = $this->project('Unrelated Job');

        $this->actingAs($this->lead);
        $this->post("/projects/{$target->id}/equipment", ['name' => 'Seismometer', 'serial_number' => 'SN-UNIQ-777']);
        $this->post("/projects/{$target->id}/shipments", ['carrier' => 'ups', 'tracking_number' => '1ZTRACK999']);
        $this->post("/projects/{$target->id}/sites", ['name' => 'Reno Test Range']);
        $this->post("/projects/{$target->id}/contacts", ['category' => 'security', 'name' => 'Dana Guard', 'phone' => '775-555-4242']);

        foreach (['SN-UNIQ-777', '1ZTRACK999', 'Reno Test Range', 'Dana Guard', '555-4242'] as $term) {
            $ids = $this->ids($this->search($term));
            $this->assertContains($target->id, $ids, "Search '{$term}' should find the target project.");
            $this->assertNotContains($decoy->id, $ids, "Search '{$term}' should not match the decoy.");
        }
    }

    public function test_name_search_still_works(): void
    {
        $target = $this->project('Helium Recovery Upgrade');
        $this->project('Something Else');

        $ids = $this->ids($this->search('Helium'));
        $this->assertContains($target->id, $ids);
        $this->assertCount(1, $ids);
    }

    public function test_search_is_org_scoped(): void
    {
        $otherOrg = Organization::factory()->create();
        $otherUser = User::factory()->create(['organization_id' => $otherOrg->id, 'is_active' => true]);
        $otherUser->assignRole('Super Admin');
        $otherProject = Project::create([
            'organization_id' => $otherOrg->id, 'created_by' => $otherUser->id, 'owner_id' => $otherUser->id,
            'name' => 'Foreign Job', 'project_number' => 'QL-PROJ-X-1', 'status' => ProjectStatus::New->value, 'created_via' => 'manual',
        ]);
        $this->actingAs($otherUser)->post("/projects/{$otherProject->id}/equipment", ['name' => 'Unit', 'serial_number' => 'SHARED-SERIAL']);

        // Our admin (different org) must not see the other org's project by that serial.
        $ids = $this->ids($this->search('SHARED-SERIAL'));
        $this->assertNotContains($otherProject->id, $ids);
    }
}
