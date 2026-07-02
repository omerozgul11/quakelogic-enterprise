<?php

namespace Tests\Feature\Procurement;

use App\Models\Organization;
use App\Models\User;
use App\Modules\Procurement\Models\PurchaseOrder;
use App\Modules\Procurement\Models\Supplier;
use App\Modules\Procurement\Services\PurchaseOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Purchase order lifecycle never emails the vendor automatically. Marking a PO
 * "Sent" (service or POST /sent) is a status-only action — the vendor is only
 * ever emailed through the explicit "Email vendor" action (see DocumentSendTest).
 */
class PurchaseOrderEmailTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->org = Organization::factory()->create();
        $this->user = User::factory()->create(['organization_id' => $this->org->id, 'is_active' => true, 'email_verified_at' => now()]);
    }

    private function supplier(?string $email): Supplier
    {
        return Supplier::create([
            'organization_id' => $this->org->id,
            'created_by' => $this->user->id,
            'owner_id' => $this->user->id,
            'code' => 'SUP-'.random_int(1000, 9999),
            'name' => 'Acme Instruments GmbH',
            'status' => 'active',
            'email' => $email,
            'currency' => 'USD',
            'address_line1' => '1 Sensor Way',
            'city' => 'Reno',
            'state' => 'NV',
        ]);
    }

    private function draftPo(Supplier $supplier): PurchaseOrder
    {
        $po = PurchaseOrder::create([
            'organization_id' => $this->org->id,
            'created_by' => $this->user->id,
            'procurement_supplier_id' => $supplier->id,
            'number' => 'PO-2026-'.random_int(1000, 9999),
            'status' => 'draft',
            'order_date' => now()->toDateString(),
            'currency' => 'USD',
            'tax_rate' => 0,
            'shipping_amount' => 0,
        ]);
        $po->items()->create(['organization_id' => $this->org->id, 'description' => 'Triaxial Seismic Sensor', 'sku' => 'SP-100', 'quantity_ordered' => 2, 'unit_cost' => 100, 'line_total' => 200, 'position' => 1]);
        app(PurchaseOrderService::class)->recalcTotals($po);

        return $po->fresh();
    }

    private function service(): PurchaseOrderService
    {
        return app(PurchaseOrderService::class);
    }

    public function test_full_request_to_send_lifecycle_never_emails_the_vendor(): void
    {
        $supplier = $this->supplier('vendor@acme.test');
        $po = $this->draftPo($supplier);

        // Purchase request → approval → mark sent. None of these email the vendor.
        $this->service()->submit($po);
        $this->assertSame('pending_approval', $po->fresh()->status->value);

        $this->service()->approve($po, $this->user->id);
        $this->assertSame('approved', $po->fresh()->status->value);

        $this->service()->markSent($po);

        Mail::assertNothingSent();
        $po->refresh();
        $this->assertSame('sent', $po->status->value);
        $this->assertNull($po->emailed_at);
    }

    public function test_mark_sent_does_not_email_the_primary_contact_either(): void
    {
        $supplier = $this->supplier(null);
        $supplier->contacts()->create(['organization_id' => $this->org->id, 'name' => 'Jana Vendor', 'email' => 'jana@acme.test', 'is_primary' => true]);
        $po = $this->draftPo($supplier);

        $this->service()->markSent($po);

        Mail::assertNothingSent();
        $this->assertNull($po->fresh()->emailed_at);
    }

    public function test_mark_sent_without_any_vendor_email_is_status_only(): void
    {
        $supplier = $this->supplier(null);
        $po = $this->draftPo($supplier);

        $this->service()->markSent($po);

        Mail::assertNothingSent();
        $po->refresh();
        $this->assertSame('sent', $po->status->value);
        $this->assertNull($po->emailed_at);
    }

    public function test_send_route_marks_sent_without_emailing_the_vendor(): void
    {
        foreach (['access procurement', 'view procurement', 'manage purchase orders', 'approve purchase orders'] as $p) {
            Permission::findOrCreate($p, 'web');
        }
        $this->user->givePermissionTo(['access procurement', 'view procurement', 'manage purchase orders', 'approve purchase orders']);

        $supplier = $this->supplier('vendor@acme.test');
        $po = $this->draftPo($supplier);
        $this->service()->approve($po, $this->user->id);

        $this->actingAs($this->user)
            ->post("/procurement/purchase-orders/{$po->id}/sent")
            ->assertRedirect();

        Mail::assertNothingSent();
        $this->assertSame('sent', $po->fresh()->status->value);
        $this->assertNull($po->fresh()->emailed_at);
    }
}
