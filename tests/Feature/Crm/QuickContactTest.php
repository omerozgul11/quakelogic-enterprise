<?php

namespace Tests\Feature\Crm;

use App\Models\Crm\QuickContact;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class QuickContactTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $manager;   // has "manage contacts"
    private User $viewer;    // access crm, but read-only

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesPermissionsSeeder::class);
        $this->org = Organization::factory()->create();

        $this->manager = User::factory()->create(['organization_id' => $this->org->id]);
        $this->manager->assignRole('Business Development Manager');

        $this->viewer = User::factory()->create(['organization_id' => $this->org->id]);
        $this->viewer->assignRole('Read Only');
    }

    public function test_index_lists_org_quick_contacts_pinned_first(): void
    {
        QuickContact::create([
            'organization_id' => $this->org->id, 'created_by' => $this->manager->id,
            'name' => 'Generic Vendor Line', 'category' => 'vendor', 'phone' => '555-000-1111',
        ]);
        QuickContact::create([
            'organization_id' => $this->org->id, 'created_by' => $this->manager->id,
            'name' => 'Chase Wire Transfer Department', 'category' => 'banking',
            'phone' => '855-536-1269', 'is_pinned' => true,
        ]);

        $this->actingAs($this->viewer)->get('/crm/quick-contacts')->assertInertia(fn (Assert $p) => $p
            ->component('Crm/QuickContacts/Index')
            ->has('contacts', 2)
            ->where('contacts.0.name', 'Chase Wire Transfer Department')
            ->where('contacts.0.category_label', 'Banking & Finance')
            ->where('can.manage', false)
        );
    }

    public function test_manager_can_create_quick_contact(): void
    {
        $this->actingAs($this->manager)->post('/crm/quick-contacts', [
            'name' => 'Chase Wire Transfer Department',
            'organization_name' => 'Chase Bank',
            'category' => 'banking',
            'phone' => '855-536-1269',
            'is_pinned' => true,
        ])->assertRedirect();

        $this->assertDatabaseHas('crm_quick_contacts', [
            'organization_id' => $this->org->id,
            'name' => 'Chase Wire Transfer Department',
            'category' => 'banking',
            'phone' => '855-536-1269',
            'is_pinned' => true,
            'created_by' => $this->manager->id,
        ]);
    }

    public function test_create_validates_required_fields_and_category(): void
    {
        $this->actingAs($this->manager)
            ->from('/crm/quick-contacts')
            ->post('/crm/quick-contacts', ['name' => '', 'category' => 'not-a-category'])
            ->assertSessionHasErrors(['name', 'category']);
    }

    public function test_viewer_cannot_create_quick_contact(): void
    {
        $this->actingAs($this->viewer)->post('/crm/quick-contacts', [
            'name' => 'Should Fail', 'category' => 'other',
        ])->assertForbidden();

        $this->assertDatabaseMissing('crm_quick_contacts', ['name' => 'Should Fail']);
    }

    public function test_manager_can_update_quick_contact(): void
    {
        $contact = QuickContact::create([
            'organization_id' => $this->org->id, 'created_by' => $this->manager->id,
            'name' => 'Old Name', 'category' => 'other',
        ]);

        $this->actingAs($this->manager)->put("/crm/quick-contacts/{$contact->id}", [
            'name' => 'New Name', 'category' => 'support', 'phone' => '555-222-3333',
        ])->assertRedirect();

        $this->assertDatabaseHas('crm_quick_contacts', [
            'id' => $contact->id, 'name' => 'New Name', 'category' => 'support',
        ]);
    }

    public function test_destroy_soft_deletes(): void
    {
        $contact = QuickContact::create([
            'organization_id' => $this->org->id, 'created_by' => $this->manager->id,
            'name' => 'Remove Me', 'category' => 'other',
        ]);

        $this->actingAs($this->manager)->delete("/crm/quick-contacts/{$contact->id}")->assertRedirect();

        $this->assertSoftDeleted('crm_quick_contacts', ['id' => $contact->id]);
    }

    public function test_cannot_touch_another_orgs_quick_contact(): void
    {
        $otherOrg = Organization::factory()->create();
        $foreign = QuickContact::create([
            'organization_id' => $otherOrg->id, 'created_by' => null,
            'name' => 'Foreign Desk', 'category' => 'other',
        ]);

        $this->actingAs($this->manager)
            ->put("/crm/quick-contacts/{$foreign->id}", ['name' => 'Hijacked', 'category' => 'other'])
            ->assertForbidden();
        $this->actingAs($this->manager)
            ->delete("/crm/quick-contacts/{$foreign->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('crm_quick_contacts', ['id' => $foreign->id, 'name' => 'Foreign Desk']);
    }

    public function test_index_is_scoped_to_own_organization(): void
    {
        $otherOrg = Organization::factory()->create();
        QuickContact::create([
            'organization_id' => $otherOrg->id, 'created_by' => null,
            'name' => 'Foreign Desk', 'category' => 'other',
        ]);
        QuickContact::create([
            'organization_id' => $this->org->id, 'created_by' => $this->manager->id,
            'name' => 'Mine', 'category' => 'other',
        ]);

        $this->actingAs($this->manager)->get('/crm/quick-contacts')->assertInertia(fn (Assert $p) => $p
            ->has('contacts', 1)
            ->where('contacts.0.name', 'Mine')
        );
    }
}
