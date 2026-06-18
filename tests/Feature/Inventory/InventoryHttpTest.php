<?php

namespace Tests\Feature\Inventory;

use App\Models\Organization;
use App\Models\User;
use App\Modules\Inventory\Models\Product;
use App\Modules\Inventory\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryHttpTest extends TestCase
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

    public function test_user_with_access_can_view_inventory_section(): void
    {
        $this->actingAs($this->manager)->get('/inventory')->assertOk();
        $this->actingAs($this->manager)->get('/inventory/products')->assertOk();
        $this->actingAs($this->manager)->get('/inventory/warehouses')->assertOk();
        $this->actingAs($this->manager)->get('/inventory/movements')->assertOk();
    }

    public function test_roleless_user_cannot_reach_inventory(): void
    {
        $stranger = User::factory()->create(['organization_id' => $this->org->id]);
        $this->actingAs($stranger)->get('/inventory')->assertForbidden();
    }

    public function test_manager_can_create_a_product(): void
    {
        $response = $this->actingAs($this->manager)->post('/inventory/products', [
            'sku' => 'TEST-001',
            'name' => 'Test Sensor',
            'type' => 'finished_good',
            'unit_cost' => 100,
            'unit_price' => 250,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('inventory_products', [
            'sku' => 'TEST-001',
            'organization_id' => $this->org->id,
            'created_by' => $this->manager->id,
        ]);
    }

    public function test_duplicate_sku_in_same_org_is_rejected(): void
    {
        Product::factory()->create(['organization_id' => $this->org->id, 'created_by' => $this->manager->id, 'sku' => 'DUP-1']);

        $this->actingAs($this->manager)
            ->post('/inventory/products', ['sku' => 'DUP-1', 'name' => 'Clash', 'type' => 'good'])
            ->assertSessionHasErrors('sku');
    }

    public function test_read_only_cannot_create_products(): void
    {
        $this->actingAs($this->readOnly)->get('/inventory/products')->assertOk();

        $this->actingAs($this->readOnly)
            ->post('/inventory/products', ['sku' => 'NO-1', 'name' => 'Nope', 'type' => 'good'])
            ->assertForbidden();
    }

    public function test_products_are_organization_scoped(): void
    {
        $otherOrg = Organization::factory()->create();
        $otherUser = User::factory()->create(['organization_id' => $otherOrg->id]);
        $foreign = Product::factory()->create(['organization_id' => $otherOrg->id, 'created_by' => $otherUser->id]);

        $this->actingAs($this->manager)->get("/inventory/products/{$foreign->id}")->assertForbidden();
    }

    public function test_receive_endpoint_increases_on_hand(): void
    {
        $product = Product::factory()->create(['organization_id' => $this->org->id, 'created_by' => $this->manager->id]);
        $warehouse = Warehouse::factory()->create(['organization_id' => $this->org->id, 'created_by' => $this->manager->id]);

        $this->actingAs($this->manager)->post("/inventory/products/{$product->id}/receive", [
            'warehouse_id' => $warehouse->id,
            'quantity' => 15,
            'unit_cost' => 8,
        ])->assertRedirect();

        $this->assertDatabaseHas('inventory_stocks', [
            'inventory_product_id' => $product->id,
            'inventory_warehouse_id' => $warehouse->id,
            'quantity_on_hand' => '15.000',
        ]);
    }

    public function test_issue_beyond_stock_fails_gracefully_with_flash_error(): void
    {
        $product = Product::factory()->create(['organization_id' => $this->org->id, 'created_by' => $this->manager->id]);
        $warehouse = Warehouse::factory()->create(['organization_id' => $this->org->id, 'created_by' => $this->manager->id]);

        $this->actingAs($this->manager)
            ->from("/inventory/products/{$product->id}")
            ->post("/inventory/products/{$product->id}/issue", ['warehouse_id' => $warehouse->id, 'quantity' => 5])
            ->assertRedirect("/inventory/products/{$product->id}")
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('inventory_movements', ['inventory_product_id' => $product->id, 'type' => 'issue']);
    }

    public function test_cannot_receive_into_another_orgs_warehouse(): void
    {
        $product = Product::factory()->create(['organization_id' => $this->org->id, 'created_by' => $this->manager->id]);
        $otherOrg = Organization::factory()->create();
        $otherUser = User::factory()->create(['organization_id' => $otherOrg->id]);
        $foreignWarehouse = Warehouse::factory()->create(['organization_id' => $otherOrg->id, 'created_by' => $otherUser->id]);

        $this->actingAs($this->manager)
            ->post("/inventory/products/{$product->id}/receive", ['warehouse_id' => $foreignWarehouse->id, 'quantity' => 1, 'unit_cost' => 1])
            ->assertSessionHasErrors('warehouse_id');
    }
}
