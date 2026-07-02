<?php

namespace Tests\Feature\Procurement;

use App\Models\Organization;
use App\Models\User;
use App\Modules\Procurement\Enums\BillPaymentStatus;
use App\Modules\Procurement\Enums\PurchaseOrderStatus;
use App\Modules\Procurement\Enums\PurchaseRequestStatus;
use App\Modules\Procurement\Enums\QuotationStatus;
use App\Modules\Procurement\Models\Bill;
use App\Modules\Procurement\Models\PurchaseOrder;
use App\Modules\Procurement\Models\PurchaseRequest;
use App\Modules\Procurement\Models\Supplier;
use App\Modules\Procurement\Services\BillService;
use App\Modules\Procurement\Services\PurchaseRequestService;
use App\Modules\Procurement\Services\QuotationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * The ported procurement flow: Purchase Request → (Quotation) → Purchase Order →
 * Bill → payments, with approvals and recurring bills.
 */
class PurchaseWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->org = Organization::factory()->create();
        $this->user = User::factory()->create(['organization_id' => $this->org->id, 'is_active' => true, 'email_verified_at' => now()]);
    }

    private function supplier(): Supplier
    {
        return Supplier::create([
            'organization_id' => $this->org->id, 'created_by' => $this->user->id, 'owner_id' => $this->user->id,
            'code' => 'SUP-'.random_int(1000, 9999), 'name' => 'Acme Instruments', 'status' => 'active', 'currency' => 'USD',
            'email' => 'vendor@acme.test',
        ]);
    }

    private function draftRequest(): PurchaseRequest
    {
        $pr = PurchaseRequest::create([
            'organization_id' => $this->org->id, 'created_by' => $this->user->id, 'requester_id' => $this->user->id,
            'number' => 'PR-'.random_int(1000, 9999), 'title' => 'Seismic sensors', 'status' => PurchaseRequestStatus::Draft, 'currency' => 'USD',
        ]);
        $pr->items()->create(['organization_id' => $this->org->id, 'description' => 'Triaxial sensor', 'quantity' => 4, 'unit_cost' => 100, 'tax_rate' => 10, 'line_total' => 400, 'position' => 0]);
        app(PurchaseRequestService::class)->recalcTotals($pr);

        return $pr->fresh();
    }

    public function test_purchase_request_totals_include_per_line_tax(): void
    {
        $pr = $this->draftRequest();
        $this->assertEqualsWithDelta(400.0, (float) $pr->subtotal, 0.001);
        $this->assertEqualsWithDelta(40.0, (float) $pr->tax_amount, 0.001);   // 10% of 400
        $this->assertEqualsWithDelta(440.0, (float) $pr->total, 0.001);
    }

    public function test_request_submit_approve_and_convert_to_order(): void
    {
        $svc = app(PurchaseRequestService::class);
        $pr = $this->draftRequest();

        $svc->submit($pr);
        $this->assertSame('pending_approval', $pr->fresh()->status->value);

        $svc->approve($pr, $this->user->id);
        $this->assertSame('approved', $pr->fresh()->status->value);

        $po = $svc->convertToPurchaseOrder($pr->fresh(), $this->supplier()->id, $this->user->id);

        $this->assertInstanceOf(PurchaseOrder::class, $po);
        $this->assertSame(PurchaseOrderStatus::Draft, $po->status);
        $this->assertSame($pr->id, $po->procurement_purchase_request_id);
        $this->assertSame(1, $po->items()->count());
        $this->assertEqualsWithDelta(400.0, (float) $po->subtotal, 0.001);
        $this->assertSame('converted', $pr->fresh()->status->value);
    }

    public function test_converting_an_unapproved_request_is_blocked(): void
    {
        $this->expectException(\RuntimeException::class);
        app(PurchaseRequestService::class)->convertToPurchaseOrder($this->draftRequest(), $this->supplier()->id, $this->user->id);
    }

    public function test_quotation_flow_accepts_into_a_purchase_order(): void
    {
        $prSvc = app(PurchaseRequestService::class);
        $quSvc = app(QuotationService::class);
        $pr = $this->draftRequest();
        $prSvc->approve($pr, $this->user->id);

        $quotation = $prSvc->convertToQuotation($pr->fresh(), $this->supplier()->id, $this->user->id);
        $this->assertSame(QuotationStatus::Draft, $quotation->status);
        $this->assertEqualsWithDelta(440.0, (float) $quotation->total, 0.001);

        $quSvc->send($quotation);
        $quSvc->markReceived($quotation->fresh());
        $po = $quSvc->accept($quotation->fresh(), $this->user->id);

        $this->assertSame($quotation->id, $po->procurement_quotation_id);
        $this->assertSame($pr->id, $po->procurement_purchase_request_id);
        $this->assertSame('accepted', $quotation->fresh()->status->value);
        $this->assertSame('converted', $pr->fresh()->status->value);
    }

    public function test_bill_from_po_tracks_payments_and_approval(): void
    {
        $supplier = $this->supplier();
        $po = PurchaseOrder::create([
            'organization_id' => $this->org->id, 'created_by' => $this->user->id, 'procurement_supplier_id' => $supplier->id,
            'number' => 'PO-'.random_int(1000, 9999), 'status' => PurchaseOrderStatus::Approved, 'currency' => 'USD', 'tax_rate' => 0, 'shipping_amount' => 0,
        ]);
        $po->items()->create(['organization_id' => $this->org->id, 'description' => 'Sensor', 'quantity_ordered' => 4, 'unit_cost' => 100, 'line_total' => 400, 'position' => 0]);
        app(\App\Modules\Procurement\Services\PurchaseOrderService::class)->recalcTotals($po);

        $billSvc = app(BillService::class);
        $bill = $billSvc->createFromPurchaseOrder($po->fresh(), $this->user->id);

        $this->assertSame($po->id, $bill->procurement_purchase_order_id);
        $this->assertEqualsWithDelta(400.0, (float) $bill->total, 0.001);
        $this->assertSame(BillPaymentStatus::Unpaid, $bill->payment_status);

        // A straight payment counts immediately.
        $billSvc->recordPayment($bill, ['amount' => 100, 'paid_on' => now()->toDateString()], $this->user->id, false);
        $this->assertSame('partially_paid', $bill->fresh()->payment_status->value);
        $this->assertEqualsWithDelta(100.0, (float) $bill->fresh()->amount_paid, 0.001);

        // A payment requiring approval does NOT count until approved.
        $pending = $billSvc->recordPayment($bill->fresh(), ['amount' => 300, 'paid_on' => now()->toDateString()], $this->user->id, true);
        $this->assertEqualsWithDelta(100.0, (float) $bill->fresh()->amount_paid, 0.001);
        $this->assertSame('partially_paid', $bill->fresh()->payment_status->value);

        $billSvc->approvePayment($pending, $this->user->id);
        $this->assertEqualsWithDelta(400.0, (float) $bill->fresh()->amount_paid, 0.001);
        $this->assertSame('paid', $bill->fresh()->payment_status->value);
    }

    public function test_recurring_bill_generates_a_due_copy(): void
    {
        $supplier = $this->supplier();
        $template = Bill::create([
            'organization_id' => $this->org->id, 'created_by' => $this->user->id, 'procurement_supplier_id' => $supplier->id,
            'number' => 'BILL-'.random_int(1000, 9999), 'currency' => 'USD', 'payment_status' => BillPaymentStatus::Unpaid,
            'recurring' => true, 'recurring_frequency' => 'monthly', 'recurring_total_cycles' => 3, 'recurring_cycles' => 0,
            'next_recurring_date' => now()->subDay()->toDateString(), 'bill_date' => now()->subMonth()->toDateString(),
        ]);
        $template->items()->create(['organization_id' => $this->org->id, 'description' => 'Monthly service', 'quantity' => 1, 'unit_cost' => 250, 'line_total' => 250, 'position' => 0]);
        app(BillService::class)->recalcTotals($template);

        $made = app(BillService::class)->generateDueRecurring($this->org->id);

        $this->assertSame(1, $made);
        $this->assertSame(1, $template->fresh()->recurring_cycles);
        $this->assertNotNull($template->fresh()->next_recurring_date);
        // The generated copy is a normal (non-template) bill pointing back at the template.
        $copy = Bill::where('recurring_parent_id', $template->id)->first();
        $this->assertNotNull($copy);
        $this->assertEqualsWithDelta(250.0, (float) $copy->total, 0.001);
        $this->assertFalse((bool) $copy->recurring);
    }

    public function test_http_create_submit_and_approve_a_request(): void
    {
        foreach (['access procurement', 'view procurement', 'manage purchase requests', 'approve purchase requests'] as $p) {
            Permission::findOrCreate($p, 'web');
        }
        $this->user->givePermissionTo(['access procurement', 'view procurement', 'manage purchase requests', 'approve purchase requests']);

        $this->actingAs($this->user)
            ->post('/procurement/purchase-requests', [
                'title' => 'Field kit',
                'currency' => 'USD',
                'items' => [['description' => 'Cable', 'quantity' => 2, 'unit_cost' => 25, 'tax_rate' => 0]],
            ])->assertRedirect();

        $pr = PurchaseRequest::where('organization_id', $this->org->id)->firstOrFail();
        $this->assertEqualsWithDelta(50.0, (float) $pr->total, 0.001);

        $this->actingAs($this->user)->post("/procurement/purchase-requests/{$pr->id}/submit")->assertRedirect();
        $this->assertSame('pending_approval', $pr->fresh()->status->value);

        $this->actingAs($this->user)->post("/procurement/purchase-requests/{$pr->id}/approve")->assertRedirect();
        $this->assertSame('approved', $pr->fresh()->status->value);
    }
}
