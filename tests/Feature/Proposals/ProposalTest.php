<?php

namespace Tests\Feature\Proposals;

use App\Models\Organization;
use App\Models\ProposalSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProposalTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;
    private User $proposalManager;
    private User $proposalWriter;
    private User $financeUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesPermissionsSeeder::class);
        $this->organization = Organization::factory()->create();

        $this->proposalManager = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->proposalManager->assignRole('Proposal Manager');

        $this->proposalWriter = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->proposalWriter->assignRole('Proposal Writer');

        $this->financeUser = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->financeUser->assignRole('Finance');
    }

    public function test_proposal_manager_can_create_proposal(): void
    {
        $response = $this->actingAs($this->proposalManager)->post('/proposals', [
            'project_name' => 'Cyber Security Operations Support',
            'agency_name' => 'CISA',
            'proposal_value' => 2500000,
            'due_date' => now()->addDays(45)->format('Y-m-d'),
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('proposal_submissions', [
            'project_name' => 'Cyber Security Operations Support',
            'organization_id' => $this->organization->id,
        ]);
    }

    public function test_proposal_gets_sequential_number(): void
    {
        $this->actingAs($this->proposalManager)->post('/proposals', [
            'project_name' => 'First Proposal',
            'agency_name' => 'NSA',
            'proposal_value' => 1000000,
        ]);

        $proposal = ProposalSubmission::where('organization_id', $this->organization->id)->first();
        $this->assertMatchesRegularExpression('/^QL-\d{4}-\d{4}$/', $proposal->proposal_number);
    }

    public function test_finance_user_cannot_view_proposal_list(): void
    {
        $response = $this->actingAs($this->financeUser)->get('/proposals');
        $response->assertStatus(403);
    }

    public function test_proposal_manager_can_transition_status(): void
    {
        $proposal = ProposalSubmission::factory()->create([
            'organization_id' => $this->organization->id,
            'status' => 'draft',
            'owner_id' => $this->proposalManager->id,
        ]);

        $response = $this->actingAs($this->proposalManager)->post("/proposals/{$proposal->id}/transition", [
            'status' => 'in_progress',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('proposal_submissions', [
            'id' => $proposal->id,
            'status' => 'in_progress',
        ]);
    }

    public function test_proposal_writer_can_only_see_assigned_proposals(): void
    {
        $ownedProposal = ProposalSubmission::factory()->create([
            'organization_id' => $this->organization->id,
            'owner_id' => $this->proposalWriter->id,
        ]);

        $otherProposal = ProposalSubmission::factory()->create([
            'organization_id' => $this->organization->id,
            'owner_id' => $this->proposalManager->id,
        ]);

        // Writer can see their own proposal
        $response = $this->actingAs($this->proposalWriter)->get("/proposals/{$ownedProposal->id}");
        $response->assertStatus(200);

        // Writer cannot see the other proposal
        $response = $this->actingAs($this->proposalWriter)->get("/proposals/{$otherProposal->id}");
        $response->assertStatus(403);
    }
}
