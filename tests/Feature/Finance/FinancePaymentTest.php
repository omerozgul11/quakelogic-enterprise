<?php

namespace Tests\Feature\Finance;

use App\Enums\InvoiceStatus;
use App\Models\Company;
use App\Models\Crm\Invoice;
use App\Models\Organization;
use App\Models\User;
use App\Modules\Finance\Enums\PaymentIntentStatus;
use App\Modules\Finance\Payments\FakePaymentProvider;
use App\Modules\Finance\Payments\PaymentProviderFactory;
use App\Modules\Finance\Services\CreditNoteService;
use App\Modules\Finance\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancePaymentTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $user;
    private PaymentService $payments;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = Organization::factory()->create();
        $this->user = User::factory()->create(['organization_id' => $this->org->id]);
        $this->payments = app(PaymentService::class);
    }

    private function invoice(float $total): Invoice
    {
        $company = Company::factory()->create(['organization_id' => $this->org->id, 'created_by' => $this->user->id]);

        return Invoice::create([
            'organization_id' => $this->org->id, 'created_by' => $this->user->id, 'company_id' => $company->id,
            'number' => 'QL-INV-'.uniqid(), 'kind' => 'invoice', 'status' => 'sent',
            'total' => $total, 'amount_paid' => 0, 'currency' => 'USD',
        ]);
    }

    public function test_factory_returns_fake_by_default_and_degrades_unconfigured_providers(): void
    {
        $this->assertInstanceOf(FakePaymentProvider::class, PaymentProviderFactory::make('fake'));
        // Stripe without credentials degrades to fake.
        $this->assertInstanceOf(FakePaymentProvider::class, PaymentProviderFactory::make('stripe'));
    }

    public function test_create_checkout_opens_a_pending_intent_with_a_url(): void
    {
        $invoice = $this->invoice(1000);

        $intent = $this->payments->createCheckout($invoice, 1000, $this->user->id);

        $this->assertSame(PaymentIntentStatus::Pending, $intent->status);
        $this->assertSame('fake', $intent->provider);
        $this->assertNotNull($intent->checkout_url);
        $this->assertNotNull($intent->reference);
    }

    public function test_capture_records_a_payment_and_marks_the_invoice_paid(): void
    {
        $invoice = $this->invoice(1000);
        $intent = $this->payments->createCheckout($invoice, 1000, $this->user->id);

        $this->payments->capture($intent, $this->user->id);

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Paid, $invoice->status);
        $this->assertSame('1000.00', $invoice->amount_paid);
        $this->assertSame(1, $invoice->payments()->where('status', 'completed')->count());
        $this->assertSame(PaymentIntentStatus::Paid, $intent->fresh()->status);
    }

    public function test_partial_capture_marks_the_invoice_partially_paid(): void
    {
        $invoice = $this->invoice(1000);
        $intent = $this->payments->createCheckout($invoice, 400, $this->user->id);

        $this->payments->capture($intent, $this->user->id);

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::PartiallyPaid, $invoice->status);
        $this->assertSame('400.00', $invoice->amount_paid);
    }

    public function test_capture_is_idempotent(): void
    {
        $invoice = $this->invoice(500);
        $intent = $this->payments->createCheckout($invoice, 500, $this->user->id);

        $this->payments->capture($intent, $this->user->id);
        $this->payments->capture($intent->fresh(), $this->user->id); // second call is a no-op

        $this->assertSame(1, $invoice->payments()->count());
    }

    public function test_credit_note_numbers_are_sequential(): void
    {
        $service = app(CreditNoteService::class);
        $first = $service->issue($this->org->id, $this->user->id, ['amount' => 100]);
        $second = $service->issue($this->org->id, $this->user->id, ['amount' => 50]);

        $year = now()->year;
        $this->assertSame("CN-{$year}-0001", $first->number);
        $this->assertSame("CN-{$year}-0002", $second->number);
    }
}
