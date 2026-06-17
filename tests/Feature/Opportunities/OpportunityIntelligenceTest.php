<?php

namespace Tests\Feature\Opportunities;

use App\Models\Opportunity;
use App\Models\Organization;
use App\Models\User;
use App\Services\Opportunities\OpportunityHealthService;
use App\Services\Opportunities\OpportunityMatchingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpportunityIntelligenceTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesPermissionsSeeder::class);
        $this->org = Organization::factory()->create();
    }

    private function user(string $role, array $profile = []): User
    {
        $u = User::factory()->create(['organization_id' => $this->org->id, ...$profile]);
        $u->assignRole($role);

        return $u;
    }

    public function test_matching_scores_and_recommends_the_best_fit(): void
    {
        $seismic = $this->user('Business Development Manager', [
            'pipeline_keywords' => ['Seismic', 'Earthquake', 'Shake Table'],
            'product_expertise' => ['Shake Tables', 'Seismographs'],
            'industry_expertise' => ['Research'],
        ]);
        $renewals = $this->user('Sales Representative', [
            'pipeline_keywords' => ['Renewal', 'Maintenance', 'Support'],
        ]);

        $opp = Opportunity::factory()->create([
            'organization_id' => $this->org->id,
            'title' => 'Seismic shake table for earthquake research',
            'description' => 'Procurement of a seismic shake table for an earthquake engineering research lab.',
        ]);

        $matching = app(OpportunityMatchingService::class);
        $matching->scoreOpportunity($opp);

        $seismicState = $opp->userStates()->where('user_id', $seismic->id)->first();
        $renewalState = $opp->userStates()->where('user_id', $renewals->id)->first();

        $this->assertNotNull($seismicState);
        $this->assertGreaterThan((float) $renewalState->match_score, (float) $seismicState->match_score);
        $this->assertTrue($seismicState->is_recommended);
        $this->assertSame('primary', $seismicState->recommended_role);
    }

    public function test_matching_preserves_a_users_reaction(): void
    {
        $user = $this->user('Business Development Manager', ['pipeline_keywords' => ['Seismic']]);
        $opp = Opportunity::factory()->create(['organization_id' => $this->org->id, 'title' => 'Seismic monitoring']);

        // User reacts first; scoring must not wipe their reaction.
        $opp->userStates()->create([
            'organization_id' => $this->org->id,
            'user_id' => $user->id,
            'reaction' => 'interested',
            'reacted_at' => now(),
        ]);

        app(OpportunityMatchingService::class)->scoreOpportunity($opp);

        $state = $opp->userStates()->where('user_id', $user->id)->first();
        $this->assertSame('interested', $state->reaction->value);
        $this->assertNotNull($state->match_score);
    }

    public function test_health_is_critical_for_overdue_unworked_opportunity(): void
    {
        $opp = Opportunity::factory()->create([
            'organization_id' => $this->org->id,
            'due_date' => now()->subDays(3),
            'assignment_stage' => 'unassigned',
            'last_activity_at' => null,
        ]);

        $health = app(OpportunityHealthService::class)->score($opp);
        $this->assertSame('critical', $health['category']);
        $this->assertLessThan(40, $health['score']);
    }

    public function test_health_is_healthy_for_fresh_active_opportunity(): void
    {
        $opp = Opportunity::factory()->create([
            'organization_id' => $this->org->id,
            'due_date' => now()->addDays(30),
            'assignment_stage' => 'submitted',
            'last_activity_at' => now(),
        ]);

        $health = app(OpportunityHealthService::class)->score($opp);
        $this->assertSame('healthy', $health['category']);
        $this->assertGreaterThanOrEqual(70, $health['score']);
    }

    public function test_escalation_reminds_owner_after_24h_and_does_not_repeat(): void
    {
        $owner = $this->user('Sales Representative');
        $opp = Opportunity::factory()->create([
            'organization_id' => $this->org->id,
            'owner_id' => $owner->id,
            'assigned_to' => $owner->id,
            'assignment_stage' => 'assigned',
            'assigned_at' => now()->subHours(25),
            'assignment_escalation_level' => 0,
        ]);

        $this->artisan('opportunities:escalate')->assertExitCode(0);

        $opp->refresh();
        $this->assertSame(24, $opp->assignment_escalation_level);
        $this->assertDatabaseHas('opportunity_events', ['opportunity_id' => $opp->id, 'type' => 'escalated']);
        $this->assertGreaterThan(0, $owner->fresh()->notifications()->count());

        // Running again at the same tier must not escalate or re-notify.
        $before = $owner->fresh()->notifications()->count();
        $this->artisan('opportunities:escalate')->assertExitCode(0);
        $this->assertSame(24, $opp->fresh()->assignment_escalation_level);
        $this->assertSame($before, $owner->fresh()->notifications()->count());
    }

    public function test_escalation_skips_claimed_opportunities(): void
    {
        $owner = $this->user('Sales Representative');
        $opp = Opportunity::factory()->create([
            'organization_id' => $this->org->id,
            'owner_id' => $owner->id,
            'assignment_stage' => 'in_progress', // already claimed → not escalated
            'assigned_at' => now()->subHours(100),
            'assignment_escalation_level' => 0,
        ]);

        $this->artisan('opportunities:escalate')->assertExitCode(0);
        $this->assertSame(0, $opp->fresh()->assignment_escalation_level);
    }
}
