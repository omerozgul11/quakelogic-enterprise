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

    public function test_count_without_a_warehouse_auto_creates_a_default_and_sets_on_hand(): void
    {
        $product = Product::factory()->create(['organization_id' => $this->org->id, 'created_by' => $this->manager->id]);
        $this->assertSame(0, Warehouse::where('organization_id', $this->org->id)->count());

        // No warehouse_id supplied — should still set the on-hand quantity.
        $this->actingAs($this->manager)
            ->post("/inventory/products/{$product->id}/count", ['counted_quantity' => 25])
            ->assertRedirect();

        $warehouse = Warehouse::where('organization_id', $this->org->id)->firstOrFail();
        $this->assertSame('MAIN', $warehouse->code);
        $this->assertDatabaseHas('inventory_stocks', [
            'inventory_product_id' => $product->id,
            'inventory_warehouse_id' => $warehouse->id,
            'quantity_on_hand' => '25.000',
        ]);
    }

    public function test_adjust_without_a_warehouse_uses_the_default(): void
    {
        $product = Product::factory()->create(['organization_id' => $this->org->id, 'created_by' => $this->manager->id]);

        $this->actingAs($this->manager)
            ->post("/inventory/products/{$product->id}/adjust", ['delta' => 7])
            ->assertRedirect();

        $warehouse = Warehouse::where('organization_id', $this->org->id)->firstOrFail();
        $this->assertDatabaseHas('inventory_stocks', [
            'inventory_product_id' => $product->id,
            'inventory_warehouse_id' => $warehouse->id,
            'quantity_on_hand' => '7.000',
        ]);
    }

    public function test_product_can_be_created_with_an_image(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');

        $this->actingAs($this->manager)->post('/inventory/products', [
            'sku' => 'IMG-1', 'name' => 'With Photo', 'type' => 'good',
            'image' => \Illuminate\Http\UploadedFile::fake()->image('photo.jpg', 240, 240),
        ])->assertRedirect();

        $product = Product::where('sku', 'IMG-1')->firstOrFail();
        $this->assertNotNull($product->image_path);
        \Illuminate\Support\Facades\Storage::disk('local')->assertExists($product->image_path);
    }

    public function test_deleting_a_movement_reverses_stock_and_rechains_balances(): void
    {
        $product = Product::factory()->create(['organization_id' => $this->org->id, 'created_by' => $this->manager->id]);
        $warehouse = Warehouse::factory()->create(['organization_id' => $this->org->id, 'created_by' => $this->manager->id]);

        // Two receives: +15 then +10 → on-hand 25.
        $this->actingAs($this->manager)->post("/inventory/products/{$product->id}/receive", ['warehouse_id' => $warehouse->id, 'quantity' => 15, 'unit_cost' => 5]);
        $this->actingAs($this->manager)->post("/inventory/products/{$product->id}/receive", ['warehouse_id' => $warehouse->id, 'quantity' => 10, 'unit_cost' => 5]);

        $movements = \App\Modules\Inventory\Models\Movement::where('inventory_product_id', $product->id)->orderBy('id')->get();
        [$first, $second] = [$movements[0], $movements[1]];

        // Delete the FIRST receive — on-hand drops to 10 and the later entry's
        // running balance re-chains to 10.
        $this->actingAs($this->manager)->delete("/inventory/movements/{$first->id}")->assertRedirect();

        $this->assertDatabaseMissing('inventory_movements', ['id' => $first->id]);
        $this->assertDatabaseHas('inventory_stocks', [
            'inventory_product_id' => $product->id,
            'inventory_warehouse_id' => $warehouse->id,
            'quantity_on_hand' => '10.000',
        ]);
        $this->assertSame('10.000', $second->fresh()->quantity_after);
    }

    public function test_read_only_cannot_delete_a_movement(): void
    {
        $product = Product::factory()->create(['organization_id' => $this->org->id, 'created_by' => $this->manager->id]);
        $warehouse = Warehouse::factory()->create(['organization_id' => $this->org->id, 'created_by' => $this->manager->id]);
        $this->actingAs($this->manager)->post("/inventory/products/{$product->id}/receive", ['warehouse_id' => $warehouse->id, 'quantity' => 3, 'unit_cost' => 1]);
        $movement = \App\Modules\Inventory\Models\Movement::where('inventory_product_id', $product->id)->firstOrFail();

        $this->actingAs($this->readOnly)->delete("/inventory/movements/{$movement->id}")->assertForbidden();
        $this->assertDatabaseHas('inventory_movements', ['id' => $movement->id]);
    }

    public function test_importing_a_spreadsheet_creates_products_and_keeps_extra_columns(): void
    {
        $csv = "SKU,Name,Price,Cost,Currency,Category,Quantity,Voltage,Color\n"
            ."IMP-100,Seismic Sensor,250,100,USD,Sensors,12,12V,Black\n"
            ."IMP-101,Cable Kit,40,15,EUR,Accessories,5,,Blue\n";
        $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('products.csv', $csv);

        $this->actingAs($this->manager)->post('/inventory/products/import', ['file' => $file])->assertRedirect();

        $p = Product::where('organization_id', $this->org->id)->where('sku', 'IMP-100')->firstOrFail();
        $this->assertSame('Seismic Sensor', $p->name);
        $this->assertSame('Sensors', $p->category);
        $this->assertSame('250.0000', $p->unit_price);
        // Unrecognised columns are kept verbatim on the product.
        $this->assertSame('12V', $p->metadata['Voltage'] ?? null);
        $this->assertSame('Black', $p->metadata['Color'] ?? null);
        // A quantity column sets the on-hand.
        $this->assertSame(12.0, (float) $p->fresh()->totalOnHand());

        $this->assertDatabaseHas('inventory_products', ['organization_id' => $this->org->id, 'sku' => 'IMP-101', 'currency' => 'EUR']);
    }

    public function test_re_importing_updates_existing_products_by_sku(): void
    {
        $first = "SKU,Name,Price\nUPD-1,Original Name,100\n";
        $this->actingAs($this->manager)->post('/inventory/products/import', ['file' => \Illuminate\Http\UploadedFile::fake()->createWithContent('a.csv', $first)]);

        $second = "SKU,Name,Price\nUPD-1,Renamed,180\n";
        $this->actingAs($this->manager)->post('/inventory/products/import', ['file' => \Illuminate\Http\UploadedFile::fake()->createWithContent('b.csv', $second)]);

        $this->assertSame(1, Product::where('organization_id', $this->org->id)->where('sku', 'UPD-1')->count());
        $this->assertDatabaseHas('inventory_products', ['sku' => 'UPD-1', 'name' => 'Renamed', 'unit_price' => '180.0000']);
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
