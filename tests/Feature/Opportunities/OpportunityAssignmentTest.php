<?php

namespace Tests\Feature\Opportunities;

use App\Models\Opportunity;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpportunityAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;
    private User $admin;
    private User $repA;
    private User $repB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesPermissionsSeeder::class);
        $this->organization = Organization::factory()->create();

        $this->admin = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->admin->assignRole('Super Admin');

        // Sales Representatives can view/create opportunities but cannot assign.
        $this->repA = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->repA->assignRole('Sales Representative');
        $this->repB = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->repB->assignRole('Sales Representative');
    }

    private function unowned(): Opportunity
    {
        return Opportunity::factory()->create([
            'organization_id' => $this->organization->id,
            'owner_id' => null,
            'assigned_to' => null,
            'ownership_locked' => false,
            'assignment_stage' => 'unassigned',
        ]);
    }

    public function test_user_can_claim_and_lock_an_unlocked_opportunity(): void
    {
        $opp = $this->unowned();

        $this->from("/opportunities/{$opp->id}")
            ->actingAs($this->repA)
            ->post("/opportunities/{$opp->id}/claim")
            ->assertRedirect();

        $this->assertDatabaseHas('opportunities', [
            'id' => $opp->id,
            'owner_id' => $this->repA->id,
            'ownership_locked' => 1,
            'assignment_stage' => 'in_progress',
        ]);
        $this->assertDatabaseHas('opportunity_events', [
            'opportunity_id' => $opp->id,
            'type' => 'claimed',
            'user_id' => $this->repA->id,
        ]);
    }

    public function test_in_progress_reaction_claims_and_locks(): void
    {
        $opp = $this->unowned();

        $this->from("/opportunities/{$opp->id}")
            ->actingAs($this->repA)
            ->post("/opportunities/{$opp->id}/react", ['reaction' => 'in_progress'])
            ->assertRedirect();

        $this->assertDatabaseHas('opportunities', [
            'id' => $opp->id,
            'owner_id' => $this->repA->id,
            'ownership_locked' => 1,
        ]);
        $this->assertDatabaseHas('opportunity_user_states', [
            'opportunity_id' => $opp->id,
            'user_id' => $this->repA->id,
            'reaction' => 'in_progress',
        ]);
    }

    public function test_reaction_is_recorded_without_claiming(): void
    {
        $opp = $this->unowned();

        $this->actingAs($this->repA)
            ->post("/opportunities/{$opp->id}/react", ['reaction' => 'interested'])
            ->assertRedirect();

        $this->assertDatabaseHas('opportunity_user_states', [
            'opportunity_id' => $opp->id,
            'user_id' => $this->repA->id,
            'reaction' => 'interested',
        ]);
        // Reacting (other than In Progress) must not claim ownership.
        $this->assertDatabaseHas('opportunities', [
            'id' => $opp->id,
            'owner_id' => null,
            'ownership_locked' => 0,
        ]);
    }

    public function test_other_user_cannot_claim_a_locked_opportunity(): void
    {
        $opp = Opportunity::factory()->create([
            'organization_id' => $this->organization->id,
            'owner_id' => $this->repA->id,
            'ownership_locked' => true,
            'assignment_stage' => 'in_progress',
        ]);

        $this->from("/opportunities/{$opp->id}")
            ->actingAs($this->repB)
            ->post("/opportunities/{$opp->id}/claim")
            ->assertSessionHas('error');

        // Ownership is unchanged — still repA.
        $this->assertDatabaseHas('opportunities', [
            'id' => $opp->id,
            'owner_id' => $this->repA->id,
        ]);
    }

    public function test_admin_can_reassign_a_locked_opportunity(): void
    {
        $opp = Opportunity::factory()->create([
            'organization_id' => $this->organization->id,
            'owner_id' => $this->repA->id,
            'ownership_locked' => true,
            'assignment_stage' => 'in_progress',
        ]);

        $this->from("/opportunities/{$opp->id}")
            ->actingAs($this->admin)
            ->post("/opportunities/{$opp->id}/assign", ['user_id' => $this->repB->id])
            ->assertRedirect();

        $this->assertDatabaseHas('opportunities', [
            'id' => $opp->id,
            'owner_id' => $this->repB->id,
            'assignment_stage' => 'assigned',
        ]);
        $this->assertDatabaseHas('opportunity_events', [
            'opportunity_id' => $opp->id,
            'type' => 'reassigned',
        ]);
    }

    public function test_non_manager_cannot_assign(): void
    {
        $opp = $this->unowned();

        $this->actingAs($this->repA)
            ->post("/opportunities/{$opp->id}/assign", ['user_id' => $this->repB->id])
            ->assertForbidden();
    }

    public function test_owner_can_advance_stage_and_records_event(): void
    {
        $opp = Opportunity::factory()->create([
            'organization_id' => $this->organization->id,
            'owner_id' => $this->repA->id,
            'ownership_locked' => true,
            'assignment_stage' => 'in_progress',
        ]);

        $this->from("/opportunities/{$opp->id}")
            ->actingAs($this->repA)
            ->post("/opportunities/{$opp->id}/stage", ['stage' => 'under_review'])
            ->assertRedirect();

        $this->assertDatabaseHas('opportunities', [
            'id' => $opp->id,
            'assignment_stage' => 'under_review',
        ]);
        $this->assertDatabaseHas('opportunity_events', [
            'opportunity_id' => $opp->id,
            'type' => 'stage_changed',
        ]);
    }
}
