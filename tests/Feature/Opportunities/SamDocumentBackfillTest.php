<?php

namespace Tests\Feature\Opportunities;

use App\Models\Opportunity;
use App\Models\Organization;
use App\Models\User;
use App\Services\BidSources\OpportunityDocumentService;
use App\Services\BidSources\SamGov\SamGovClient;
use App\Services\BidSources\SamGov\SamGovConnector;
use App\Services\BidSources\SamGov\SamThrottledException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Backfilling SAM.gov solicitation documents for opportunities that synced
 * without them — and, critically, that a transient SAM throttle (HTTP 429) is
 * NOT recorded as a permanent "no documents" miss, so the files still get
 * pulled once the daily quota resets.
 */
class SamDocumentBackfillTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = Organization::factory()->create();
        $this->user = User::factory()->create(['organization_id' => $this->org->id]);
    }

    /** Bind a real SAM client (so it honours Http::fake) behind the connector. */
    private function docService(): OpportunityDocumentService
    {
        $client = new SamGovClient('TEST-KEY', 'https://api.sam.gov/opportunities/v2');
        $this->app->instance(SamGovConnector::class, new SamGovConnector($client));

        return $this->app->make(OpportunityDocumentService::class);
    }

    private function docLessSamOpportunity(): Opportunity
    {
        return Opportunity::create([
            'organization_id' => $this->org->id,
            'created_by' => $this->user->id,
            'title' => 'Seismic Sensors — Brand Name',
            'source' => 'sam_gov',
            'external_id' => 'notice-abc-123',
            'posted_date' => now()->toDateString(),
            'status' => 'new',
            'raw_source_data' => ['_id' => 'notice-abc-123', 'title' => 'Seismic Sensors'],
        ]);
    }

    private function fakeSam(array $response, int $status = 200): void
    {
        Http::fake(['api.sam.gov/*' => Http::response($response, $status)]);
    }

    public function test_successful_fetch_pulls_and_lists_documents(): void
    {
        $opp = $this->docLessSamOpportunity();
        $this->fakeSam(['opportunitiesData' => [[
            'noticeId' => 'notice-abc-123',
            'title' => 'Seismic Sensors',
            'resourceLinks' => [
                'https://api.sam.gov/prod/opportunities/v1/resources/files/aaa/download',
                'https://api.sam.gov/prod/opportunities/v1/resources/files/bbb/download',
            ],
        ]]]);

        $status = $this->docService()->ensure($opp, force: true);

        $this->assertSame('pulled', $status);
        $this->assertCount(2, $opp->fresh()->raw_source_data['resourceLinks']);
        $this->assertCount(2, $this->docService()->list($opp->fresh()));
    }

    public function test_throttle_is_not_cached_as_a_permanent_miss(): void
    {
        $opp = $this->docLessSamOpportunity();

        // First call is throttled (quota exhausted); the next call (quota reset)
        // succeeds — the whole point is that the throttle didn't permanently block it.
        Http::fake(['api.sam.gov/*' => Http::sequence()
            ->push(['message' => 'Message throttled out'], 429)
            ->push(['opportunitiesData' => [[
                'noticeId' => 'notice-abc-123',
                'resourceLinks' => ['https://api.sam.gov/prod/opportunities/v1/resources/files/ccc/download'],
            ]]], 200),
        ]);

        $status = $this->docService()->ensure($opp, force: true);
        $this->assertSame('throttled', $status);
        // No links merged, and crucially NOT recorded as a confirmed "no documents".
        $this->assertArrayNotHasKey('resourceLinks', (array) $opp->fresh()->raw_source_data);
        $this->assertFalse(Cache::has("opp_docs_none:{$opp->id}"));

        // Quota back → the very next real attempt pulls the documents.
        $status = $this->docService()->ensure($opp->fresh(), force: true);
        $this->assertSame('pulled', $status);
        $this->assertCount(1, $opp->fresh()->raw_source_data['resourceLinks']);
    }

    public function test_genuine_no_attachments_is_cached_and_not_re_probed(): void
    {
        $opp = $this->docLessSamOpportunity();
        $this->fakeSam(['opportunitiesData' => [[
            'noticeId' => 'notice-abc-123',
            'resourceLinks' => [],
        ]]]);

        $status = $this->docService()->ensure($opp, force: true);

        $this->assertSame('none', $status);
        $this->assertTrue(Cache::has("opp_docs_none:{$opp->id}"));
        // A non-forced retry is served from cache ('none_cached') — no second SAM
        // call — so the daily "pull for all" run doesn't re-probe empty notices.
        $this->assertSame('none_cached', $this->docService()->ensure($opp->fresh()));
    }

    public function test_client_throws_on_throttle_status(): void
    {
        Http::fake(['api.sam.gov/*' => Http::response(['message' => 'throttled'], 429)]);
        $client = new SamGovClient('TEST-KEY', 'https://api.sam.gov/opportunities/v2');

        $this->expectException(SamThrottledException::class);
        $client->getOpportunity('notice-abc-123', now());
    }
}
