<?php

namespace Tests\Feature\Manufacturing;

use App\Models\Organization;
use App\Models\User;
use App\Modules\Inventory\Models\Product;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Manufacturing\Models\Bom;
use App\Modules\Manufacturing\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManufacturingHttpTest extends TestCase
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

    public function test_user_with_access_can_view_manufacturing_section(): void
    {
        $this->actingAs($this->manager)->get('/manufacturing')->assertOk();
        $this->actingAs($this->manager)->get('/manufacturing/boms')->assertOk();
        $this->actingAs($this->manager)->get('/manufacturing/work-orders')->assertOk();
        $this->actingAs($this->manager)->get('/manufacturing/work-orders/create')->assertOk();
    }

    public function test_roleless_user_cannot_reach_manufacturing(): void
    {
        $stranger = User::factory()->create(['organization_id' => $this->org->id]);
        $this->actingAs($stranger)->get('/manufacturing')->assertForbidden();
    }

    public function test_manager_can_create_a_bom_with_components(): void
    {
        $output = Product::factory()->create(['organization_id' => $this->org->id, 'created_by' => $this->manager->id]);
        $component = Product::factory()->create(['organization_id' => $this->org->id, 'created_by' => $this->manager->id]);

        $this->actingAs($this->manager)->post('/manufacturing/boms', [
            'inventory_product_id' => $output->id,
            'name' => 'Test Assembly',
            'output_quantity' => 1,
            'items' => [['inventory_product_id' => $component->id, 'quantity_per' => 4]],
        ])->assertRedirect();

        $bom = Bom::where('organization_id', $this->org->id)->first();
        $this->assertNotNull($bom);
        $this->assertSame(1, $bom->items()->count());
    }

    public function test_read_only_cannot_create_a_bom(): void
    {
        $output = Product::factory()->create(['organization_id' => $this->org->id, 'created_by' => $this->manager->id]);

        $this->actingAs($this->readOnly)->get('/manufacturing/boms')->assertOk();
        $this->actingAs($this->readOnly)->post('/manufacturing/boms', [
            'inventory_product_id' => $output->id, 'name' => 'No', 'output_quantity' => 1,
            'items' => [['inventory_product_id' => $output->id, 'quantity_per' => 1]],
        ])->assertForbidden();
    }

    public function test_boms_are_organization_scoped(): void
    {
        $otherOrg = Organization::factory()->create();
        $otherUser = User::factory()->create(['organization_id' => $otherOrg->id]);
        $foreign = Bom::factory()->create(['organization_id' => $otherOrg->id, 'created_by' => $otherUser->id]);

        $this->actingAs($this->manager)->get("/manufacturing/boms/{$foreign->id}")->assertForbidden();
    }

    public function test_completing_a_work_order_builds_finished_goods_into_inventory(): void
    {
        $inventory = app(InventoryService::class);
        $warehouse = Warehouse::factory()->create(['organization_id' => $this->org->id, 'created_by' => $this->manager->id]);
        $output = Product::factory()->create(['organization_id' => $this->org->id, 'created_by' => $this->manager->id, 'unit_cost' => 0]);
        $component = Product::factory()->create(['organization_id' => $this->org->id, 'created_by' => $this->manager->id, 'unit_cost' => 0]);
        $inventory->receive($component, $warehouse, 100, 3, ['actor_id' => $this->manager->id]);

        $bom = Bom::factory()->create(['organization_id' => $this->org->id, 'created_by' => $this->manager->id, 'inventory_product_id' => $output->id, 'output_quantity' => 1]);
        $bom->items()->create(['organization_id' => $this->org->id, 'inventory_product_id' => $component->id, 'quantity_per' => 2, 'position' => 0]);

        // Create the work order via HTTP (defaults to the product's active BOM).
        $this->actingAs($this->manager)->post('/manufacturing/work-orders', [
            'inventory_product_id' => $output->id,
            'inventory_warehouse_id' => $warehouse->id,
            'quantity_planned' => 5,
        ])->assertRedirect();
        $wo = WorkOrder::where('organization_id', $this->org->id)->first();

        $this->actingAs($this->manager)->post("/manufacturing/work-orders/{$wo->id}/release")->assertRedirect();
        $this->actingAs($this->manager)->post("/manufacturing/work-orders/{$wo->id}/complete")->assertRedirect();

        $this->assertSame('completed', $wo->fresh()->status->value);
        $this->assertSame('90.000', $inventory->stockFor($component, $warehouse)->quantity_on_hand); // 100 - 2*5
        $this->assertSame('5.000', $inventory->stockFor($output, $warehouse)->quantity_on_hand);
    }

    public function test_read_only_cannot_create_a_work_order(): void
    {
        $warehouse = Warehouse::factory()->create(['organization_id' => $this->org->id, 'created_by' => $this->manager->id]);
        $output = Product::factory()->create(['organization_id' => $this->org->id, 'created_by' => $this->manager->id]);

        $this->actingAs($this->readOnly)->post('/manufacturing/work-orders', [
            'inventory_product_id' => $output->id,
            'inventory_warehouse_id' => $warehouse->id,
            'quantity_planned' => 1,
        ])->assertForbidden();
    }
}
