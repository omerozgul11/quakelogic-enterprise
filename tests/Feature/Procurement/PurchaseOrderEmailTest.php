<?php

namespace Tests\Feature\Procurement;

use App\Models\Organization;
use App\Models\User;
use App\Modules\Procurement\Mail\PurchaseOrderMail;
use App\Modules\Procurement\Models\PurchaseOrder;
use App\Modules\Procurement\Models\Supplier;
use App\Modules\Procurement\Services\PurchaseOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Purchase order → vendor email: marking a PO "Sent" emails it to the supplier
 * (or its primary contact), records emailed_at, and the full
 * request → approve → send lifecycle works.
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

    public function test_full_request_to_send_lifecycle_emails_the_vendor(): void
    {
        $supplier = $this->supplier('vendor@acme.test');
        $po = $this->draftPo($supplier);

        // Purchase request → approval → send to vendor.
        $this->service()->submit($po);
        $this->assertSame('pending_approval', $po->fresh()->status->value);

        $this->service()->approve($po, $this->user->id);
        $this->assertSame('approved', $po->fresh()->status->value);

        $this->service()->markSent($po);

        Mail::assertSent(PurchaseOrderMail::class, fn (PurchaseOrderMail $m) => $m->hasTo('vendor@acme.test') && $m->purchaseOrder->id === $po->id);
        $po->refresh();
        $this->assertSame('sent', $po->status->value);
        $this->assertNotNull($po->emailed_at);
    }

    public function test_falls_back_to_primary_contact_email(): void
    {
        $supplier = $this->supplier(null);
        $supplier->contacts()->create(['organization_id' => $this->org->id, 'name' => 'Jana Vendor', 'email' => 'jana@acme.test', 'is_primary' => true]);
        $po = $this->draftPo($supplier);

        $this->service()->markSent($po);

        Mail::assertSent(PurchaseOrderMail::class, fn (PurchaseOrderMail $m) => $m->hasTo('jana@acme.test'));
        $this->assertNotNull($po->fresh()->emailed_at);
    }

    public function test_send_without_any_vendor_email_is_graceful(): void
    {
        $supplier = $this->supplier(null);
        $po = $this->draftPo($supplier);

        $this->service()->markSent($po);

        Mail::assertNothingSent();
        $po->refresh();
        $this->assertSame('sent', $po->status->value);
        $this->assertNull($po->emailed_at);
    }

    public function test_send_route_emails_the_vendor(): void
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

        Mail::assertSent(PurchaseOrderMail::class, fn (PurchaseOrderMail $m) => $m->hasTo('vendor@acme.test'));
        $this->assertSame('sent', $po->fresh()->status->value);
    }
}
