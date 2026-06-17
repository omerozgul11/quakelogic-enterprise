<?php

namespace Tests\Feature\Opportunities;

use App\Models\AiAnalysis;
use App\Models\FollowUpSchedule;
use App\Models\Opportunity;
use App\Models\Organization;
use App\Models\ProposalSubmission;
use App\Models\User;
use App\Services\BidSources\BidPrime\BidPrimeImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase5IntegrationTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesPermissionsSeeder::class);
        $this->org = Organization::factory()->create();
        $this->admin = User::factory()->create(['organization_id' => $this->org->id]);
        $this->admin->assignRole('Super Admin'); // has "review ai extraction" + "update opportunities"
    }

    public function test_accepting_win_probability_writes_to_proposal(): void
    {
        $proposal = ProposalSubmission::factory()->create(['organization_id' => $this->org->id, 'win_probability' => null]);

        $analysis = AiAnalysis::create([
            'organization_id' => $this->org->id,
            'created_by' => $this->admin->id,
            'subject_type' => ProposalSubmission::class,
            'subject_id' => $proposal->id,
            'analysis_type' => 'win_probability',
            'ai_provider' => 'fake',
            'status' => 'needs_review',
            'output' => ['probability' => 0.8],
        ]);

        $this->actingAs($this->admin)
            ->post("/ai/{$analysis->id}/review", ['human_decision' => 'accepted'])
            ->assertRedirect();

        $this->assertSame(80, (int) $proposal->fresh()->win_probability);
    }

    public function test_accepting_go_no_go_writes_to_opportunity(): void
    {
        $opp = Opportunity::factory()->create(['organization_id' => $this->org->id, 'go_no_go_decision' => null]);

        $analysis = AiAnalysis::create([
            'organization_id' => $this->org->id,
            'created_by' => $this->admin->id,
            'subject_type' => Opportunity::class,
            'subject_id' => $opp->id,
            'analysis_type' => 'go_no_go',
            'ai_provider' => 'fake',
            'status' => 'needs_review',
            'output' => ['recommendation' => 'GO', 'rationale' => 'Strong fit', 'win_probability' => 0.7],
        ]);

        $this->actingAs($this->admin)
            ->post("/ai/{$analysis->id}/review", ['human_decision' => 'accepted'])
            ->assertRedirect();

        $fresh = $opp->fresh();
        $this->assertSame('GO', $fresh->go_no_go_decision);
        $this->assertSame('Strong fit', $fresh->go_no_go_notes);
        $this->assertSame(70, (int) $fresh->probability_of_win);
        $this->assertSame($this->admin->id, $fresh->go_no_go_decided_by);
    }

    public function test_bidprime_import_creates_opportunities_with_creator(): void
    {
        $owner = User::factory()->create(['organization_id' => $this->org->id]);

        $import = app(BidPrimeImportService::class)->import($this->org->fresh(), []);

        $this->assertSame('completed', $import->status);
        $this->assertGreaterThan(0, $import->total_created);
        $opp = Opportunity::where('organization_id', $this->org->id)->where('source', 'bidprime')->first();
        $this->assertNotNull($opp);
        $this->assertNotNull($opp->created_by); // created_by is NOT NULL — must be set
    }

    public function test_follow_up_schedule_generates_a_follow_up(): void
    {
        $proposal = ProposalSubmission::factory()->create([
            'organization_id' => $this->org->id,
            'owner_id' => $this->admin->id,
            'status' => 'submitted',
        ]);

        FollowUpSchedule::create([
            'organization_id' => $this->org->id,
            'name' => 'Post-submission nudge',
            'trigger_event' => 'proposal_submitted',
            'delay_days' => 0,
            'follow_up_type' => 'reminder',
            'subject_template' => 'Follow up on {proposal_number}',
            'message_template' => 'Checking in on {project_name}.',
            'is_active' => true,
            'assign_to_owner' => true,
            'intervals_days' => [0],
        ]);

        $this->artisan('follow-ups:generate')->assertExitCode(0);

        $this->assertDatabaseHas('follow_ups', [
            'proposal_submission_id' => $proposal->id,
            'type' => 'reminder',
            'created_by' => $this->admin->id,
            'is_automated' => true,
        ]);
    }

    public function test_opportunity_edit_page_renders(): void
    {
        $opp = Opportunity::factory()->create(['organization_id' => $this->org->id]);

        $this->actingAs($this->admin)
            ->get("/opportunities/{$opp->id}/edit")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Opportunities/Edit')->has('opportunity')->has('statuses'));
    }
}
