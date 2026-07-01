<?php

namespace Tests\Feature\Admin;

use App\Models\BidprimeEmail;
use App\Models\Opportunity;
use App\Models\Organization;
use App\Models\User;
use App\Services\BidSources\BidPrime\GmailBidPrimeIngestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * BidPrime admin review dashboard: loads for Super Admins, supports import /
 * reprocess / approve / reject, and is blocked for everyone else.
 */
class BidPrimeAdminTest extends TestCase
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
        $this->seed(\Database\Seeders\OpportunityKeywordGroupSeeder::class);

        $this->admin = User::factory()->create(['organization_id' => $this->org->id, 'is_active' => true, 'email_verified_at' => now()]);
        $this->admin->assignRole('Super Admin');
        $this->outsider = User::factory()->create(['organization_id' => $this->org->id, 'is_active' => true, 'email_verified_at' => now()]);
        $this->outsider->assignRole('Read Only');

        app(GmailBidPrimeIngestService::class)->ingest();
    }

    public function test_dashboard_loads_for_admin(): void
    {
        $this->actingAs($this->admin)->get('/admin/bidprime')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/BidPrime/Dashboard')
                ->where('stats.opps_total', 4)
                ->has('emails', 3)
                ->has('opportunities.data')
                ->has('imports'));
    }

    public function test_priority_filter_returns_only_high(): void
    {
        $this->actingAs($this->admin)->get('/admin/bidprime?priority=high')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('filters.priority', 'high'));
    }

    public function test_email_detail_shows_extracted_opportunities(): void
    {
        $email = BidprimeEmail::where('subject', 'like', '%Daily Bid Alert%')->firstOrFail();

        $this->actingAs($this->admin)->get("/admin/bidprime/emails/{$email->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/BidPrime/EmailShow')
                ->where('email.id', $email->id)
                ->has('opportunities', 2));
    }

    public function test_import_now_and_reprocess_actions(): void
    {
        $this->actingAs($this->admin)->post('/admin/bidprime/import-now')->assertRedirect();
        $this->actingAs($this->admin)->post('/admin/bidprime/reprocess-recent')->assertRedirect();
        $this->actingAs($this->admin)->post('/admin/bidprime/reprocess-failed')->assertRedirect();

        $email = BidprimeEmail::first();
        $this->actingAs($this->admin)->post("/admin/bidprime/emails/{$email->id}/reprocess")->assertRedirect();

        // Still no duplicate opportunities after all that reprocessing.
        $this->assertSame(4, Opportunity::where('source', 'bidprime')->count());
    }

    public function test_approve_and_reject_opportunity(): void
    {
        $opps = Opportunity::where('source', 'bidprime')->orderBy('id')->get();
        $approve = $opps[0];
        $reject = $opps[1];

        $this->actingAs($this->admin)->post("/admin/bidprime/opportunities/{$approve->id}/approve")->assertRedirect();
        $this->actingAs($this->admin)->post("/admin/bidprime/opportunities/{$reject->id}/reject")->assertRedirect();

        $this->assertSame('qualified', $approve->fresh()->status->value);
        $this->assertSame('no_bid', $reject->fresh()->status->value);
    }

    public function test_non_admin_is_forbidden(): void
    {
        $this->actingAs($this->outsider)->get('/admin/bidprime')->assertForbidden();
        $this->actingAs($this->outsider)->post('/admin/bidprime/import-now')->assertForbidden();
    }
}
