<?php

namespace Tests\Feature\Procurement;

use App\Models\Company;
use App\Models\Organization;
use App\Models\User;
use App\Modules\Procurement\Enums\PurchaseOrderStatus;
use App\Modules\Procurement\Models\PurchaseOrder;
use App\Modules\Procurement\Models\Supplier;
use App\Modules\Procurement\Services\PurchaseOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Editing a draft purchase order after creation, and entering tax as either a
 * percentage or a flat amount.
 */
class PurchaseOrderEditTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = Organization::factory()->create();
        $this->user = User::factory()->create(['organization_id' => $this->org->id, 'is_active' => true, 'email_verified_at' => now()]);
        foreach (['access procurement', 'view procurement', 'manage purchase orders'] as $p) {
            Permission::findOrCreate($p, 'web');
        }
        $this->user->givePermissionTo(['access procurement', 'view procurement', 'manage purchase orders']);
    }

    private function supplier(): Supplier
    {
        return Supplier::create([
            'organization_id' => $this->org->id, 'created_by' => $this->user->id, 'owner_id' => $this->user->id,
            'code' => 'SUP-'.random_int(1000, 9999), 'name' => 'Acme', 'status' => 'active', 'currency' => 'USD',
        ]);
    }

    private function draftOrder(): PurchaseOrder
    {
        $po = PurchaseOrder::create([
            'organization_id' => $this->org->id, 'created_by' => $this->user->id,
            'procurement_supplier_id' => $this->supplier()->id,
            'number' => 'PO-'.random_int(1000, 9999), 'status' => PurchaseOrderStatus::Draft,
            'order_date' => now()->toDateString(), 'currency' => 'USD',
            'tax_rate' => 0, 'tax_amount' => 0, 'shipping_amount' => 0,
        ]);
        $po->items()->create(['organization_id' => $this->org->id, 'description' => 'Widget', 'quantity_ordered' => 2, 'unit_cost' => 100, 'line_total' => 200, 'position' => 0]);
        app(PurchaseOrderService::class)->recalcTotals($po);

        return $po->fresh();
    }

    public function test_edit_page_loads_for_a_draft(): void
    {
        $po = $this->draftOrder();
        $this->actingAs($this->user)->get("/procurement/purchase-orders/{$po->id}/edit")->assertOk();
    }

    public function test_editing_a_non_draft_order_is_blocked(): void
    {
        $po = $this->draftOrder();
        $po->forceFill(['status' => PurchaseOrderStatus::Sent])->save();

        $this->actingAs($this->user)->get("/procurement/purchase-orders/{$po->id}/edit")
            ->assertRedirect(route('procurement.purchase-orders.show', $po));
    }

    public function test_update_persists_changes_with_a_flat_tax(): void
    {
        $po = $this->draftOrder();

        $this->actingAs($this->user)->put("/procurement/purchase-orders/{$po->id}", [
            'procurement_supplier_id' => $po->procurement_supplier_id,
            'currency' => 'USD',
            'tax_rate' => 0,
            'tax_amount' => 25,          // flat tax
            'shipping_amount' => 10,
            'items' => [
                ['description' => 'Widget', 'quantity_ordered' => 3, 'unit_cost' => 100],
            ],
        ])->assertRedirect();

        $po->refresh();
        $this->assertEqualsWithDelta(300.0, (float) $po->subtotal, 0.001);
        $this->assertEqualsWithDelta(25.0, (float) $po->tax_amount, 0.001);   // fixed, not scaled
        $this->assertEqualsWithDelta(335.0, (float) $po->total, 0.001);       // 300 + 25 + 10
        $this->assertSame(1, $po->items()->count());
    }

    public function test_a_client_can_be_set_and_cleared_on_a_purchase_order(): void
    {
        $company = Company::factory()->create(['organization_id' => $this->org->id]);
        $po = $this->draftOrder();

        // Set the client.
        $this->actingAs($this->user)->put("/procurement/purchase-orders/{$po->id}", [
            'procurement_supplier_id' => $po->procurement_supplier_id,
            'company_id' => $company->id,
            'currency' => 'USD', 'tax_rate' => 0, 'shipping_amount' => 0,
            'items' => [['description' => 'Widget', 'quantity_ordered' => 1, 'unit_cost' => 100]],
        ])->assertRedirect();
        $this->assertSame($company->id, $po->fresh()->company_id);

        // Clear it (client is optional).
        $this->actingAs($this->user)->put("/procurement/purchase-orders/{$po->id}", [
            'procurement_supplier_id' => $po->procurement_supplier_id,
            'currency' => 'USD', 'tax_rate' => 0, 'shipping_amount' => 0,
            'items' => [['description' => 'Widget', 'quantity_ordered' => 1, 'unit_cost' => 100]],
        ])->assertRedirect();
        $this->assertNull($po->fresh()->company_id);
    }

    public function test_update_with_a_percentage_tax(): void
    {
        $po = $this->draftOrder();

        $this->actingAs($this->user)->put("/procurement/purchase-orders/{$po->id}", [
            'procurement_supplier_id' => $po->procurement_supplier_id,
            'currency' => 'USD',
            'tax_rate' => 10,
            'tax_amount' => 0,
            'shipping_amount' => 0,
            'items' => [
                ['description' => 'Widget', 'quantity_ordered' => 2, 'unit_cost' => 100],
            ],
        ])->assertRedirect();

        $po->refresh();
        $this->assertEqualsWithDelta(20.0, (float) $po->tax_amount, 0.001);   // 10% of 200
        $this->assertEqualsWithDelta(220.0, (float) $po->total, 0.001);
    }
}
