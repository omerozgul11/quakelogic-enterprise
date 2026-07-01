<?php

namespace Tests\Feature\Opportunities;

use App\Enums\OpportunityPriority;
use App\Models\Opportunity;
use App\Models\OpportunityKeywordGroup;
use App\Models\Organization;
use App\Models\User;
use App\Services\BidSources\BidPrime\GmailBidPrimeIngestService;
use App\Services\Opportunities\OpportunityScorer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Relevance scoring from admin-editable keyword groups: strong matches score
 * High, exclusion keywords force Not Relevant, and scoring is applied during the
 * BidPrime email import.
 */
class OpportunityScoringTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = Organization::factory()->create();
        $this->userId = User::factory()->create(['organization_id' => $this->org->id, 'is_active' => true])->id;
        $this->seed(\Database\Seeders\OpportunityKeywordGroupSeeder::class);
    }

    private function opp(array $attrs): Opportunity
    {
        return Opportunity::create(array_merge([
            'organization_id' => $this->org->id,
            'created_by' => $this->userId,
            'source' => 'manual',
            'status' => 'new',
            'title' => 'Untitled',
        ], $attrs));
    }

    private function scorer(): OpportunityScorer
    {
        return app(OpportunityScorer::class);
    }

    public function test_strong_seismic_match_scores_high(): void
    {
        $o = $this->opp([
            'title' => 'Seismic Monitoring System Upgrade',
            'description' => 'Statewide structural health monitoring with triaxial accelerometers and data acquisition.',
            'naics_code' => '334513',
            'due_date' => now()->addDays(10)->toDateString(),
            'estimated_value' => 1250000,
        ]);

        $this->scorer()->scoreAndStore($o);
        $o->refresh();

        $this->assertSame(OpportunityPriority::High, $o->priority);
        $this->assertGreaterThanOrEqual(35, $o->relevance_score);
        $this->assertNotEmpty($o->matched_keywords);
        $this->assertArrayHasKey('keyword', $o->score_breakdown);
    }

    public function test_exclusion_keyword_forces_not_relevant(): void
    {
        OpportunityKeywordGroup::where('organization_id', $this->org->id)
            ->where('is_exclusion', true)->firstOrFail()
            ->update(['keywords' => ['janitorial']]);

        $o = $this->opp(['title' => 'Janitorial Services Contract', 'description' => 'Daily janitorial cleaning of offices.']);

        $this->scorer()->scoreAndStore($o);
        $o->refresh();

        $this->assertSame(OpportunityPriority::NotRelevant, $o->priority);
        $this->assertSame(0, $o->relevance_score);
    }

    public function test_unrelated_opportunity_is_not_relevant(): void
    {
        $o = $this->opp(['title' => 'Office Furniture Procurement', 'description' => 'Ergonomic chairs and standing desks.']);

        $this->scorer()->scoreAndStore($o);
        $o->refresh();

        $this->assertSame(OpportunityPriority::NotRelevant, $o->priority);
    }

    public function test_scoring_is_applied_during_email_ingest(): void
    {
        app(GmailBidPrimeIngestService::class)->ingest();

        $seismic = Opportunity::where('source', 'bidprime')->where('solicitation_number', 'RFP-2026-0042')->firstOrFail();

        $this->assertSame(OpportunityPriority::High, $seismic->priority);
        $this->assertGreaterThan(0, $seismic->relevance_score);
        $this->assertNotEmpty($seismic->matched_keywords);
    }
}
