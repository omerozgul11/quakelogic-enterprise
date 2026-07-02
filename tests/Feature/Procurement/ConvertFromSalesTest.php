<?php

namespace Tests\Feature\Procurement;

use App\Models\Crm\Invoice;
use App\Models\Organization;
use App\Models\User;
use App\Modules\Procurement\Enums\PurchaseOrderStatus;
use App\Modules\Procurement\Enums\PurchaseRequestStatus;
use App\Modules\Procurement\Models\PurchaseOrder;
use App\Modules\Procurement\Models\PurchaseRequest;
use App\Modules\Procurement\Models\Supplier;
use App\Modules\Procurement\Services\PurchaseOrderService;
use App\Modules\Procurement\Services\PurchaseRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Raising a purchase request or purchase order from a CRM sales invoice/estimate
 * by copying its line items (the legacy "copy sale invoice/estimate" shortcut).
 */
class ConvertFromSalesTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = Organization::factory()->create();
        $this->user = User::factory()->create(['organization_id' => $this->org->id, 'is_active' => true, 'email_verified_at' => now()]);

        foreach (['access procurement', 'view procurement', 'manage purchase requests', 'manage purchase orders'] as $p) {
            Permission::findOrCreate($p, 'web');
        }
        $this->user->givePermissionTo(['access procurement', 'view procurement', 'manage purchase requests', 'manage purchase orders']);
    }

    private function invoice(?Organization $org = null, string $kind = 'invoice'): Invoice
    {
        $org ??= $this->org;
        $inv = Invoice::create([
            'organization_id' => $org->id, 'created_by' => $this->user->id,
            'number' => 'INV-'.random_int(1000, 9999), 'kind' => $kind, 'status' => 'sent',
            'currency' => 'USD', 'subtotal' => 300, 'total' => 300,
        ]);
        $inv->items()->create(['description' => 'Widget A', 'quantity' => 2, 'unit_price' => 100, 'amount' => 200, 'position' => 0]);
        $inv->items()->create(['description' => 'Widget B', 'quantity' => 1, 'unit_price' => 100, 'amount' => 100, 'position' => 1]);

        return $inv->fresh();
    }

    public function test_service_builds_a_draft_request_from_an_invoice(): void
    {
        $inv = $this->invoice();

        $pr = app(PurchaseRequestService::class)->fromCrmInvoice($inv, $this->user->id);

        $this->assertSame(PurchaseRequestStatus::Draft, $pr->status);
        $this->assertSame(2, $pr->items()->count());
        $this->assertEqualsWithDelta(300.0, (float) $pr->subtotal, 0.001);
        $this->assertStringContainsString($inv->number, $pr->title);
    }

    public function test_service_builds_a_draft_order_from_an_invoice(): void
    {
        $inv = $this->invoice(kind: 'estimate');
        $supplier = Supplier::create([
            'organization_id' => $this->org->id, 'created_by' => $this->user->id, 'owner_id' => $this->user->id,
            'code' => 'SUP-'.random_int(1000, 9999), 'name' => 'Acme', 'status' => 'active', 'currency' => 'USD',
        ]);

        $po = app(PurchaseOrderService::class)->fromCrmInvoice($inv, $supplier->id, $this->user->id);

        $this->assertSame(PurchaseOrderStatus::Draft, $po->status);
        $this->assertSame($supplier->id, $po->procurement_supplier_id);
        $this->assertSame(2, $po->items()->count());
        $this->assertEqualsWithDelta(300.0, (float) $po->total, 0.001);
    }

    public function test_http_copy_creates_a_draft_request_and_redirects(): void
    {
        $inv = $this->invoice();

        $this->actingAs($this->user)
            ->post("/procurement/purchase-requests/from-invoice/{$inv->id}")
            ->assertRedirect();

        $pr = PurchaseRequest::where('organization_id', $this->org->id)->firstOrFail();
        $this->assertSame(2, $pr->items()->count());
    }

    public function test_cannot_copy_an_invoice_from_another_org(): void
    {
        $otherOrg = Organization::factory()->create();
        $foreignInvoice = $this->invoice($otherOrg);

        $this->actingAs($this->user)
            ->post("/procurement/purchase-requests/from-invoice/{$foreignInvoice->id}")
            ->assertNotFound();

        $this->assertSame(0, PurchaseRequest::where('organization_id', $this->org->id)->count());
    }
}
