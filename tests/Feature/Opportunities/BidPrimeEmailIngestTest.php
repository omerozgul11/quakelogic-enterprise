<?php

namespace Tests\Feature\Opportunities;

use App\Models\BidprimeEmail;
use App\Models\BidprimeImportItem;
use App\Models\Opportunity;
use App\Models\Organization;
use App\Models\User;
use App\Services\BidSources\BidPrime\GmailBidPrimeIngestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * BidPrime email ingestion: the fake inbox (3 fixture emails → 4 opportunities)
 * drives the full read → store → parse → dedup → import path with per-email
 * traceability, idempotency, and reprocessing.
 */
class BidPrimeEmailIngestTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = Organization::factory()->create();
        User::factory()->create(['organization_id' => $this->org->id, 'is_active' => true]);
    }

    private function service(): GmailBidPrimeIngestService
    {
        return app(GmailBidPrimeIngestService::class);
    }

    public function test_ingest_creates_opportunities_and_stores_emails(): void
    {
        $import = $this->service()->ingest();

        // 3 fixture emails → 4 opportunities (2 + 1 + 1).
        $this->assertSame('completed', $import->status);
        $this->assertSame(4, $import->total_created);

        $opps = Opportunity::where('organization_id', $this->org->id)->where('source', 'bidprime')->get();
        $this->assertCount(4, $opps);
        $this->assertTrue($opps->every(fn ($o) => $o->status->value === 'new'));

        $seismic = $opps->firstWhere('solicitation_number', 'RFP-2026-0042');
        $this->assertNotNull($seismic);
        $this->assertSame('334513', $seismic->naics_code);
        $this->assertSame('CA', $seismic->place_of_performance_state);
        $this->assertSame('2026-08-15', $seismic->due_date?->format('Y-m-d'));
        $this->assertSame('email', $seismic->raw_source_data['channel'] ?? null);

        // 3 emails stored, all parsed, with the raw message kept.
        $emails = BidprimeEmail::where('organization_id', $this->org->id)->get();
        $this->assertCount(3, $emails);
        $this->assertSame(4, $emails->sum('opportunities_found'));
        $this->assertTrue($emails->every(fn ($e) => $e->status === 'parsed'));
        $this->assertTrue($emails->every(fn ($e) => $e->raw_html !== null || $e->raw_text !== null));
    }

    public function test_each_opportunity_traces_back_to_its_email(): void
    {
        $this->service()->ingest();

        $items = BidprimeImportItem::with('email')->whereNotNull('bidprime_email_id')->get();
        $this->assertGreaterThanOrEqual(4, $items->count());
        foreach ($items as $item) {
            $this->assertNotNull($item->email, 'Import item should link to a stored email.');
            $this->assertSame($this->org->id, $item->email->organization_id);
        }
    }

    public function test_ingest_is_idempotent(): void
    {
        $this->service()->ingest();
        $second = $this->service()->ingest();

        // Same emails already processed → nothing re-imported, no duplicate opps.
        $this->assertSame(0, $second->total_created);
        $this->assertSame(4, Opportunity::where('source', 'bidprime')->count());
        $this->assertSame(3, BidprimeEmail::count());
    }

    public function test_reprocess_email_does_not_duplicate(): void
    {
        $this->service()->ingest();
        $digest = BidprimeEmail::where('subject', 'like', '%Daily Bid Alert%')->firstOrFail();

        $this->service()->reprocessEmail($digest);

        // Its 2 opportunities are recognised as duplicates — total unchanged.
        $this->assertSame(4, Opportunity::where('source', 'bidprime')->count());
        $this->assertSame('parsed', $digest->fresh()->status);
    }

    public function test_command_runs_against_fake_inbox(): void
    {
        $this->artisan('bidprime:ingest-email')->assertExitCode(0);

        $this->assertSame(4, Opportunity::where('source', 'bidprime')->count());
    }

    public function test_existing_manual_opportunity_is_not_overwritten(): void
    {
        // A manually-managed opportunity sharing a solicitation number must not be
        // clobbered by the email import (cross-source dedup flags, never overwrites).
        $manual = Opportunity::create([
            'organization_id' => $this->org->id,
            'created_by' => User::where('organization_id', $this->org->id)->value('id'),
            'title' => 'Manually curated seismic bid',
            'solicitation_number' => 'RFP-2026-0042',
            'source' => 'manual',
            'status' => 'pursuing',
        ]);

        $this->service()->ingest();

        $manual->refresh();
        $this->assertSame('Manually curated seismic bid', $manual->title);
        $this->assertSame('pursuing', $manual->status->value);
        $this->assertTrue((bool) $manual->is_duplicate_flagged);
    }
}
