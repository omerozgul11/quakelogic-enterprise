<?php

namespace Tests\Feature\Finance;

use App\Enums\InvoiceStatus;
use App\Models\Company;
use App\Models\Crm\Invoice;
use App\Models\Organization;
use App\Models\User;
use App\Modules\Finance\Models\CreditNote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceHttpTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $manager;
    private User $readOnly;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesPermissionsSeeder::class);
        $this->org = Organization::factory()->create();

        $this->manager = User::factory()->create(['organization_id' => $this->org->id]);
        $this->manager->assignRole('Business Development Manager');

        $this->readOnly = User::factory()->create(['organization_id' => $this->org->id]);
        $this->readOnly->assignRole('Read Only');
    }

    private function invoice(float $total = 1000): Invoice
    {
        $company = Company::factory()->create(['organization_id' => $this->org->id, 'created_by' => $this->manager->id]);

        return Invoice::create([
            'organization_id' => $this->org->id, 'created_by' => $this->manager->id, 'company_id' => $company->id,
            'number' => 'QL-INV-'.uniqid(), 'kind' => 'invoice', 'status' => 'sent',
            'total' => $total, 'amount_paid' => 0, 'currency' => 'USD',
        ]);
    }

    public function test_user_with_access_can_view_finance(): void
    {
        $this->actingAs($this->manager)->get('/finance')->assertOk();
        $this->actingAs($this->manager)->get('/finance/invoices')->assertOk();
        $this->actingAs($this->manager)->get('/finance/credit-notes')->assertOk();
    }

    public function test_roleless_user_cannot_reach_finance(): void
    {
        $stranger = User::factory()->create(['organization_id' => $this->org->id]);
        $this->actingAs($stranger)->get('/finance')->assertForbidden();
    }

    public function test_collect_then_capture_marks_the_invoice_paid(): void
    {
        $invoice = $this->invoice(1000);

        $this->actingAs($this->manager)->post("/finance/invoices/{$invoice->id}/collect", ['amount' => 1000])->assertRedirect();
        $intent = \App\Modules\Finance\Models\PaymentIntent::where('crm_invoice_id', $invoice->id)->firstOrFail();

        $this->actingAs($this->manager)->post("/finance/invoices/{$invoice->id}/intents/{$intent->id}/capture")->assertRedirect();

        $this->assertSame(InvoiceStatus::Paid, $invoice->fresh()->status);
    }

    public function test_manual_payment_recording_updates_the_invoice(): void
    {
        $invoice = $this->invoice(800);

        $this->actingAs($this->manager)->post("/finance/invoices/{$invoice->id}/record-payment", [
            'amount' => 800, 'method' => 'Wire transfer', 'reference' => 'W-123',
        ])->assertRedirect();

        $this->assertSame(InvoiceStatus::Paid, $invoice->fresh()->status);
        $this->assertSame('800.00', $invoice->fresh()->amount_paid);
    }

    public function test_read_only_cannot_collect_or_record_payments(): void
    {
        $invoice = $this->invoice();

        $this->actingAs($this->readOnly)->get('/finance/invoices')->assertOk();
        $this->actingAs($this->readOnly)->post("/finance/invoices/{$invoice->id}/collect", ['amount' => 100])->assertForbidden();
        $this->actingAs($this->readOnly)->post("/finance/invoices/{$invoice->id}/record-payment", ['amount' => 100, 'method' => 'Cash'])->assertForbidden();
    }

    public function test_invoices_are_organization_scoped(): void
    {
        $otherOrg = Organization::factory()->create();
        $otherUser = User::factory()->create(['organization_id' => $otherOrg->id]);
        $company = Company::factory()->create(['organization_id' => $otherOrg->id, 'created_by' => $otherUser->id]);
        $foreign = Invoice::create([
            'organization_id' => $otherOrg->id, 'created_by' => $otherUser->id, 'company_id' => $company->id,
            'number' => 'QL-INV-X', 'kind' => 'invoice', 'status' => 'sent', 'total' => 100, 'amount_paid' => 0, 'currency' => 'USD',
        ]);

        $this->actingAs($this->manager)->get("/finance/invoices/{$foreign->id}")->assertForbidden();
    }

    public function test_manager_can_issue_apply_and_void_a_credit_note(): void
    {
        $this->actingAs($this->manager)->post('/finance/credit-notes', ['amount' => 250, 'reason' => 'Overcharge'])->assertRedirect();
        $note = CreditNote::where('organization_id', $this->org->id)->firstOrFail();
        $this->assertStringStartsWith('CN-', $note->number);

        $this->actingAs($this->manager)->post("/finance/credit-notes/{$note->id}/apply")->assertRedirect();
        $this->assertSame('applied', $note->fresh()->status->value);

        $this->actingAs($this->manager)->post("/finance/credit-notes/{$note->id}/void")->assertRedirect();
        $this->assertSame('void', $note->fresh()->status->value);
    }

    public function test_read_only_cannot_issue_credit_notes(): void
    {
        $this->actingAs($this->readOnly)->post('/finance/credit-notes', ['amount' => 100])->assertForbidden();
    }
}
