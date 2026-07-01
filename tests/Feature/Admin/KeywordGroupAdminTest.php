<?php

namespace Tests\Feature\Admin;

use App\Models\OpportunityKeywordGroup;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admin keyword-group editor: Super Admins can manage groups; everyone else is
 * blocked by the role:Super Admin middleware.
 */
class KeywordGroupAdminTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $admin;
    private User $outsider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesPermissionsSeeder::class);
        $this->org = Organization::factory()->create();

        $this->admin = User::factory()->create(['organization_id' => $this->org->id, 'is_active' => true, 'email_verified_at' => now()]);
        $this->admin->assignRole('Super Admin');

        $this->outsider = User::factory()->create(['organization_id' => $this->org->id, 'is_active' => true, 'email_verified_at' => now()]);
        $this->outsider->assignRole('Read Only');
    }

    public function test_super_admin_can_create_update_and_delete_a_group(): void
    {
        $this->actingAs($this->admin)->get('/admin/keyword-groups')->assertOk();

        $this->actingAs($this->admin)->post('/admin/keyword-groups', [
            'name' => 'Seismic',
            'keywords' => ['seismic', 'accelerometer'],
            'naics_codes' => ['334513'],
            'weight' => 12,
            'is_exclusion' => false,
            'is_active' => true,
            'color' => 'red',
        ])->assertRedirect();

        $group = OpportunityKeywordGroup::where('organization_id', $this->org->id)->where('name', 'Seismic')->firstOrFail();
        $this->assertSame(['seismic', 'accelerometer'], $group->keywords);
        $this->assertSame(['334513'], $group->naics_codes);
        $this->assertSame(12, $group->weight);

        $this->actingAs($this->admin)->put("/admin/keyword-groups/{$group->id}", [
            'name' => 'Seismic & Monitoring',
            'keywords' => ['seismic', 'seismometer', 'monitoring system'],
            'weight' => 15,
        ])->assertRedirect();
        $group->refresh();
        $this->assertSame('Seismic & Monitoring', $group->name);
        $this->assertContains('monitoring system', $group->keywords);

        $this->actingAs($this->admin)->delete("/admin/keyword-groups/{$group->id}")->assertRedirect();
        $this->assertSoftDeleted('opportunity_keyword_groups', ['id' => $group->id]);
    }

    public function test_non_admin_is_forbidden(): void
    {
        $this->actingAs($this->outsider)->get('/admin/keyword-groups')->assertForbidden();
        $this->actingAs($this->outsider)->post('/admin/keyword-groups', ['name' => 'X'])->assertForbidden();
    }
}
