<?php

namespace Tests\Feature\Procurement;

use App\Models\Organization;
use App\Models\User;
use App\Modules\Inventory\Models\Product;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Procurement\Models\PurchaseOrder;
use App\Modules\Procurement\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcurementHttpTest extends TestCase
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

    public function test_user_with_access_can_view_procurement_section(): void
    {
        $this->actingAs($this->manager)->get('/procurement')->assertOk();
        $this->actingAs($this->manager)->get('/procurement/suppliers')->assertOk();
        $this->actingAs($this->manager)->get('/procurement/purchase-orders')->assertOk();
        $this->actingAs($this->manager)->get('/procurement/purchase-orders/create')->assertOk();
    }

    public function test_roleless_user_cannot_reach_procurement(): void
    {
        $stranger = User::factory()->create(['organization_id' => $this->org->id]);
        $this->actingAs($stranger)->get('/procurement')->assertForbidden();
    }

    public function test_manager_can_create_a_supplier(): void
    {
        $this->actingAs($this->manager)->post('/procurement/suppliers', [
            'code' => 'SUP-1', 'name' => 'Acme Co', 'payment_terms' => 'Net 30',
        ])->assertRedirect();

        $this->assertDatabaseHas('procurement_suppliers', [
            'code' => 'SUP-1', 'organization_id' => $this->org->id,
        ]);
    }

    public function test_duplicate_supplier_code_is_rejected(): void
    {
        Supplier::factory()->create(['organization_id' => $this->org->id, 'created_by' => $this->manager->id, 'code' => 'DUP']);

        $this->actingAs($this->manager)
            ->post('/procurement/suppliers', ['code' => 'DUP', 'name' => 'Clash'])
            ->assertSessionHasErrors('code');
    }

    public function test_read_only_cannot_create_a_supplier(): void
    {
        $this->actingAs($this->readOnly)->get('/procurement/suppliers')->assertOk();
        $this->actingAs($this->readOnly)
            ->post('/procurement/suppliers', ['code' => 'NO', 'name' => 'Nope'])
            ->assertForbidden();
    }

    public function test_suppliers_are_organization_scoped(): void
    {
        $otherOrg = Organization::factory()->create();
        $otherUser = User::factory()->create(['organization_id' => $otherOrg->id]);
        $foreign = Supplier::factory()->create(['organization_id' => $otherOrg->id, 'created_by' => $otherUser->id]);

        $this->actingAs($this->manager)->get("/procurement/suppliers/{$foreign->id}")->assertForbidden();
    }

    public function test_manager_can_create_a_purchase_order_with_items(): void
    {
        $supplier = Supplier::factory()->create(['organization_id' => $this->org->id, 'created_by' => $this->manager->id]);
        $warehouse = Warehouse::factory()->create(['organization_id' => $this->org->id, 'created_by' => $this->manager->id]);
        $product = Product::factory()->create(['organization_id' => $this->org->id, 'created_by' => $this->manager->id]);

        $this->actingAs($this->manager)->post('/procurement/purchase-orders', [
            'procurement_supplier_id' => $supplier->id,
            'inventory_warehouse_id' => $warehouse->id,
            'tax_rate' => 10,
            'shipping_amount' => 25,
            'items' => [
                ['inventory_product_id' => $product->id, 'description' => 'Sensor', 'quantity_ordered' => 4, 'unit_cost' => 100],
            ],
        ])->assertRedirect();

        $po = PurchaseOrder::where('organization_id', $this->org->id)->first();
        $this->assertNotNull($po);
        $this->assertSame('draft', $po->status->value);
        $this->assertSame('465.00', $po->total); // 400 subtotal + 40 tax (10%) + 25 shipping
        $this->assertDatabaseHas('procurement_purchase_order_items', [
            'procurement_purchase_order_id' => $po->id,
            'description' => 'Sensor',
            'quantity_ordered' => '4.000',
        ]);
    }

    public function test_approve_then_receive_endpoint_increases_inventory(): void
    {
        $supplier = Supplier::factory()->create(['organization_id' => $this->org->id, 'created_by' => $this->manager->id]);
        $warehouse = Warehouse::factory()->create(['organization_id' => $this->org->id, 'created_by' => $this->manager->id]);
        $product = Product::factory()->create(['organization_id' => $this->org->id, 'created_by' => $this->manager->id]);

        $this->actingAs($this->manager)->post('/procurement/purchase-orders', [
            'procurement_supplier_id' => $supplier->id,
            'inventory_warehouse_id' => $warehouse->id,
            'items' => [['inventory_product_id' => $product->id, 'description' => 'Sensor', 'quantity_ordered' => 6, 'unit_cost' => 50]],
        ]);
        $po = PurchaseOrder::where('organization_id', $this->org->id)->first();

        $this->actingAs($this->manager)->post("/procurement/purchase-orders/{$po->id}/approve")->assertRedirect();
        $this->actingAs($this->manager)->post("/procurement/purchase-orders/{$po->id}/receive", ['receive_all' => true])->assertRedirect();

        $this->assertSame('received', $po->fresh()->status->value);
        $this->assertDatabaseHas('inventory_stocks', [
            'inventory_product_id' => $product->id,
            'inventory_warehouse_id' => $warehouse->id,
            'quantity_on_hand' => '6.000',
        ]);
    }

    public function test_read_only_cannot_create_a_purchase_order(): void
    {
        $supplier = Supplier::factory()->create(['organization_id' => $this->org->id, 'created_by' => $this->manager->id]);

        $this->actingAs($this->readOnly)->post('/procurement/purchase-orders', [
            'procurement_supplier_id' => $supplier->id,
            'items' => [['description' => 'x', 'quantity_ordered' => 1, 'unit_cost' => 1]],
        ])->assertForbidden();
    }
}
