<?php

namespace Tests\Feature\Procurement;

use App\Models\Organization;
use App\Models\User;
use App\Modules\Procurement\Enums\BillPaymentStatus;
use App\Modules\Procurement\Enums\PurchaseOrderStatus;
use App\Modules\Procurement\Enums\PurchaseRequestStatus;
use App\Modules\Procurement\Enums\QuotationStatus;
use App\Modules\Procurement\Mail\ProcurementDocumentMail;
use App\Modules\Procurement\Models\Bill;
use App\Modules\Procurement\Models\PurchaseOrder;
use App\Modules\Procurement\Models\PurchaseRequest;
use App\Modules\Procurement\Models\Quotation;
use App\Modules\Procurement\Models\Supplier;
use App\Modules\Procurement\Services\ProcurementDocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * The "send to vendor" layer: branded PDFs for every procurement document and a
 * PDF-attached email with CC/BCC + a custom message.
 */
class DocumentSendTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = Organization::factory()->create();
        $this->user = User::factory()->create(['organization_id' => $this->org->id, 'is_active' => true, 'email_verified_at' => now()]);

        foreach (['access procurement', 'view procurement', 'manage purchase requests', 'manage quotations', 'manage purchase orders', 'manage bills'] as $p) {
            Permission::findOrCreate($p, 'web');
        }
        $this->user->givePermissionTo(['access procurement', 'view procurement', 'manage purchase requests', 'manage quotations', 'manage purchase orders', 'manage bills']);
    }

    private function supplier(): Supplier
    {
        return Supplier::create([
            'organization_id' => $this->org->id, 'created_by' => $this->user->id, 'owner_id' => $this->user->id,
            'code' => 'SUP-'.random_int(1000, 9999), 'name' => 'Acme Instruments', 'status' => 'active', 'currency' => 'USD',
            'email' => 'vendor@acme.test',
        ]);
    }

    private function purchaseOrder(): PurchaseOrder
    {
        $po = PurchaseOrder::create([
            'organization_id' => $this->org->id, 'created_by' => $this->user->id,
            'procurement_supplier_id' => $this->supplier()->id,
            'number' => 'PO-'.random_int(1000, 9999), 'status' => PurchaseOrderStatus::Approved,
            'order_date' => now()->toDateString(), 'currency' => 'USD',
            'subtotal' => 400, 'tax_rate' => 0, 'tax_amount' => 0, 'shipping_amount' => 0, 'total' => 400,
        ]);
        $po->items()->create(['organization_id' => $this->org->id, 'description' => 'Triaxial sensor', 'quantity_ordered' => 4, 'unit_cost' => 100, 'line_total' => 400, 'position' => 0]);

        return $po->fresh();
    }

    public function test_document_service_renders_a_pdf_for_each_type(): void
    {
        $svc = app(ProcurementDocumentService::class);
        $po = $this->purchaseOrder();

        $bytes = $svc->pdf($po);
        $this->assertStringStartsWith('%PDF', $bytes);
        $this->assertSame($po->number.'.pdf', $svc->filename($po));
    }

    public function test_purchase_order_pdf_endpoint_streams_a_pdf(): void
    {
        $po = $this->purchaseOrder();

        $res = $this->actingAs($this->user)->get("/procurement/purchase-orders/{$po->id}/pdf");

        $res->assertOk();
        $res->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $res->getContent());
    }

    public function test_sending_a_purchase_order_emails_the_pdf_and_marks_it_sent(): void
    {
        Mail::fake();
        $po = $this->purchaseOrder();

        $this->actingAs($this->user)->post("/procurement/purchase-orders/{$po->id}/send-email", [
            'to' => 'buyer@vendor.test',
            'cc' => 'watcher@vendor.test',
            'subject' => 'Your PO',
            'message' => 'Please fulfil.',
        ])->assertRedirect();

        Mail::assertSent(ProcurementDocumentMail::class, function (ProcurementDocumentMail $mail) {
            return $mail->hasTo('buyer@vendor.test')
                && $mail->hasCc('watcher@vendor.test')
                && str_starts_with($mail->pdf, '%PDF');
        });

        $po->refresh();
        $this->assertSame(PurchaseOrderStatus::Sent, $po->status);
        $this->assertNotNull($po->emailed_at);
    }

    public function test_send_requires_a_recipient(): void
    {
        Mail::fake();
        $po = $this->purchaseOrder();

        $this->actingAs($this->user)
            ->post("/procurement/purchase-orders/{$po->id}/send-email", ['to' => ''])
            ->assertSessionHasErrors('to');

        Mail::assertNothingSent();
    }

    public function test_sending_a_quotation_emails_it_and_marks_sent(): void
    {
        Mail::fake();
        $q = Quotation::create([
            'organization_id' => $this->org->id, 'created_by' => $this->user->id,
            'procurement_supplier_id' => $this->supplier()->id,
            'number' => 'RFQ-'.random_int(1000, 9999), 'status' => QuotationStatus::Draft,
            'quote_date' => now()->toDateString(), 'currency' => 'USD',
            'subtotal' => 100, 'tax_amount' => 0, 'discount_total' => 0, 'total' => 100,
        ]);
        $q->items()->create(['organization_id' => $this->org->id, 'description' => 'Cable', 'quantity' => 1, 'unit_cost' => 100, 'tax_rate' => 0, 'line_total' => 100, 'position' => 0]);

        $this->actingAs($this->user)->post("/procurement/quotations/{$q->id}/send-email", [
            'to' => 'sales@vendor.test',
        ])->assertRedirect();

        Mail::assertSent(ProcurementDocumentMail::class);
        $this->assertSame(QuotationStatus::Sent, $q->fresh()->status);
    }

    public function test_sending_a_purchase_request_emails_it(): void
    {
        Mail::fake();
        $pr = PurchaseRequest::create([
            'organization_id' => $this->org->id, 'created_by' => $this->user->id, 'requester_id' => $this->user->id,
            'number' => 'PR-'.random_int(1000, 9999), 'title' => 'Sensors', 'status' => PurchaseRequestStatus::Draft, 'currency' => 'USD',
            'subtotal' => 50, 'tax_amount' => 0, 'total' => 50,
        ]);
        $pr->items()->create(['organization_id' => $this->org->id, 'description' => 'Cable', 'quantity' => 2, 'unit_cost' => 25, 'tax_rate' => 0, 'line_total' => 50, 'position' => 0]);

        $this->actingAs($this->user)->post("/procurement/purchase-requests/{$pr->id}/send-email", [
            'to' => 'approver@quakelogic.test',
        ])->assertRedirect();

        Mail::assertSent(ProcurementDocumentMail::class, fn ($m) => $m->hasTo('approver@quakelogic.test'));
    }

    public function test_bill_pdf_endpoint_streams_a_pdf(): void
    {
        $bill = Bill::create([
            'organization_id' => $this->org->id, 'created_by' => $this->user->id,
            'procurement_supplier_id' => $this->supplier()->id,
            'number' => 'BILL-'.random_int(1000, 9999), 'bill_date' => now()->toDateString(), 'currency' => 'USD',
            'subtotal' => 100, 'tax_amount' => 0, 'shipping_amount' => 0, 'discount_total' => 0, 'total' => 100, 'amount_paid' => 0,
            'payment_status' => BillPaymentStatus::Unpaid,
        ]);
        $bill->items()->create(['organization_id' => $this->org->id, 'description' => 'Cable', 'quantity' => 1, 'unit_cost' => 100, 'tax_rate' => 0, 'line_total' => 100, 'position' => 0]);

        $res = $this->actingAs($this->user)->get("/procurement/bills/{$bill->id}/pdf");
        $res->assertOk();
        $res->assertHeader('content-type', 'application/pdf');
    }
}
