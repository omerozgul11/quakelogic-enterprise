<?php

namespace Tests\Feature\Shipments;

use App\Models\Organization;
use App\Models\ShipmentRateQuote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RateQuoteTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesPermissionsSeeder::class);
        $this->org = Organization::factory()->create();
        $this->user = User::factory()->create(['organization_id' => $this->org->id]);
        $this->user->givePermissionTo('access shipments');
    }

    public function test_user_with_access_can_view_rates_pages(): void
    {
        $this->actingAs($this->user)->get('/shipments/rates')->assertOk();
        $this->actingAs($this->user)->get('/shipments/rates/create')->assertOk();
    }

    public function test_roleless_user_cannot_reach_rates(): void
    {
        $stranger = User::factory()->create(['organization_id' => $this->org->id]);
        $this->actingAs($stranger)->get('/shipments/rates')->assertForbidden();
    }

    public function test_store_saves_a_manual_draft_quote(): void
    {
        $this->actingAs($this->user)->post('/shipments/rates', [
            'reference' => 'RFP 24-118 set to DC',
            'contact_email' => 'rep@dhl.com',
            'carrier' => 'DHL',  // normalizes to the enum value
            'service_line' => 'express',
            'origin_city' => 'Sacramento', 'origin_postal' => '95814',
            'dest_city' => 'Washington', 'dest_postal' => '20001',
            'weight' => '5',
        ])->assertRedirect();

        $this->assertDatabaseHas('shipment_rate_quotes', [
            'organization_id' => $this->org->id,
            'created_by' => $this->user->id,
            'carrier' => 'dhl',
            'reference' => 'RFP 24-118 set to DC',
            'contact_email' => 'rep@dhl.com',
            'status' => 'draft',
            'source' => 'manual',
        ]);
    }

    public function test_invalid_contact_email_is_rejected(): void
    {
        $this->actingAs($this->user)->post('/shipments/rates', [
            'carrier' => 'dhl', 'contact_email' => 'not-an-email',
        ])->assertSessionHasErrors('contact_email');
    }

    public function test_mark_requested_sets_status_and_timestamp(): void
    {
        $quote = $this->quote(['status' => 'draft']);

        $this->actingAs($this->user)->post("/shipments/rates/{$quote->ulid}/request")->assertRedirect();

        $quote->refresh();
        $this->assertSame('requested', $quote->status->value);
        $this->assertNotNull($quote->requested_at);
    }

    public function test_can_attach_and_download_a_rate_sheet_pdf(): void
    {
        Storage::fake('local');
        $quote = $this->quote();

        $this->actingAs($this->user)->post("/shipments/rates/{$quote->ulid}/document", [
            'file' => UploadedFile::fake()->create('dhl-quote.pdf', 80, 'application/pdf'),
        ])->assertRedirect();

        $quote->refresh();
        $this->assertNotNull($quote->document_path);
        $this->assertSame('dhl-quote.pdf', $quote->document_name);
        Storage::disk('local')->assertExists($quote->document_path);

        $this->actingAs($this->user)
            ->get("/shipments/rates/{$quote->ulid}/document/download")
            ->assertOk();
    }

    public function test_rejects_a_non_document_upload(): void
    {
        Storage::fake('local');
        $quote = $this->quote();

        $this->actingAs($this->user)->post("/shipments/rates/{$quote->ulid}/document", [
            'file' => UploadedFile::fake()->create('virus.exe', 10, 'application/octet-stream'),
        ])->assertSessionHasErrors('file');
    }

    public function test_can_remove_an_attached_rate_sheet(): void
    {
        Storage::fake('local');
        $quote = $this->quote();
        $this->actingAs($this->user)->post("/shipments/rates/{$quote->ulid}/document", [
            'file' => UploadedFile::fake()->create('q.pdf', 50, 'application/pdf'),
        ]);
        $quote->refresh();
        $path = $quote->document_path;

        $this->actingAs($this->user)->delete("/shipments/rates/{$quote->ulid}/document")->assertRedirect();

        $quote->refresh();
        $this->assertNull($quote->document_path);
        Storage::disk('local')->assertMissing($path);
    }

    public function test_extract_without_a_document_warns(): void
    {
        $quote = $this->quote();

        $this->actingAs($this->user)
            ->post("/shipments/rates/{$quote->ulid}/extract")
            ->assertRedirect();
        $this->assertNull($quote->fresh()->amount);
    }

    public function test_update_edits_a_quote(): void
    {
        $quote = $this->quote(['reference' => 'old']);

        $this->actingAs($this->user)->patch("/shipments/rates/{$quote->ulid}", [
            'carrier' => 'dhl', 'reference' => 'new label', 'status' => 'declined',
        ])->assertRedirect('/shipments/rates');

        $quote->refresh();
        $this->assertSame('new label', $quote->reference);
        $this->assertSame('declined', $quote->status->value);
    }

    public function test_destroy_soft_deletes(): void
    {
        $quote = $this->quote();

        $this->actingAs($this->user)->delete("/shipments/rates/{$quote->ulid}")->assertRedirect('/shipments/rates');
        $this->assertSoftDeleted('shipment_rate_quotes', ['id' => $quote->id]);
    }

    public function test_cannot_touch_another_orgs_quote(): void
    {
        $otherOrg = Organization::factory()->create();
        $quote = ShipmentRateQuote::create([
            'organization_id' => $otherOrg->id, 'carrier' => 'dhl', 'status' => 'draft',
        ]);

        $this->actingAs($this->user)->get("/shipments/rates/{$quote->ulid}/edit")->assertNotFound();
        $this->actingAs($this->user)->delete("/shipments/rates/{$quote->ulid}")->assertNotFound();
    }

    private function quote(array $attrs = []): ShipmentRateQuote
    {
        return ShipmentRateQuote::create(array_merge([
            'organization_id' => $this->org->id,
            'created_by' => $this->user->id,
            'carrier' => 'dhl',
            'status' => 'draft',
        ], $attrs));
    }
}
