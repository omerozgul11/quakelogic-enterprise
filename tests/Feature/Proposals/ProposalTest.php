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
            'company' => 'CISA',
            'solicitation_number' => 'CISA-2026-0001',
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
            'company' => 'NSA',
            'solicitation_number' => 'NSA-2026-0001',
            'proposal_value' => 1000000,
            'due_date' => now()->addDays(30)->format('Y-m-d'),
        ]);

        $proposal = ProposalSubmission::where('organization_id', $this->organization->id)->first();
        $this->assertMatchesRegularExpression('/^QL-\d{4}-\d{4}$/', $proposal->proposal_number);
    }

    public function test_finance_user_can_view_proposal_list(): void
    {
        // Collaborative visibility: every org user with "view proposals" (now
        // all roles) can view the proposals list. See ProposalSubmissionPolicy.
        $response = $this->actingAs($this->financeUser)->get('/proposals');
        $response->assertStatus(200);
    }

    public function test_proposal_manager_can_transition_status(): void
    {
        $proposal = ProposalSubmission::factory()->create([
            'organization_id' => $this->organization->id,
            'status' => 'in_progress',
            'owner_id' => $this->proposalManager->id,
        ]);

        $response = $this->actingAs($this->proposalManager)->post("/proposals/{$proposal->id}/transition", [
            'status' => 'submitted',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('proposal_submissions', [
            'id' => $proposal->id,
            'status' => 'submitted',
        ]);
    }

    public function test_proposal_writer_can_view_any_proposal_in_org(): void
    {
        // Collaborative editing model (ProposalSubmissionPolicy::view): any org
        // user can open any proposal — owned or not — so anyone can edit it.
        $ownedProposal = ProposalSubmission::factory()->create([
            'organization_id' => $this->organization->id,
            'owner_id' => $this->proposalWriter->id,
        ]);

        $otherProposal = ProposalSubmission::factory()->create([
            'organization_id' => $this->organization->id,
            'owner_id' => $this->proposalManager->id,
        ]);

        $this->actingAs($this->proposalWriter)->get("/proposals/{$ownedProposal->id}")->assertStatus(200);
        $this->actingAs($this->proposalWriter)->get("/proposals/{$otherProposal->id}")->assertStatus(200);
    }
}
