<?php

namespace Tests\Feature\Assets;

use App\Models\Organization;
use App\Models\User;
use App\Modules\AssetManagement\Models\Asset;
use App\Modules\Inventory\Models\Product;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetHttpTest extends TestCase
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

    public function test_user_with_access_can_view_assets_section(): void
    {
        $this->actingAs($this->manager)->get('/assets')->assertOk();
        $this->actingAs($this->manager)->get('/assets/registry')->assertOk();
    }

    public function test_roleless_user_cannot_reach_assets(): void
    {
        $stranger = User::factory()->create(['organization_id' => $this->org->id]);
        $this->actingAs($stranger)->get('/assets')->assertForbidden();
    }

    public function test_manager_can_create_an_asset(): void
    {
        $this->actingAs($this->manager)->post('/assets/registry', [
            'asset_tag' => 'AST-2026-9001', 'name' => 'Test Sensor', 'status' => 'active',
        ])->assertRedirect();

        $this->assertDatabaseHas('asset_assets', ['asset_tag' => 'AST-2026-9001', 'organization_id' => $this->org->id]);
    }

    public function test_duplicate_asset_tag_is_rejected(): void
    {
        Asset::factory()->create(['organization_id' => $this->org->id, 'created_by' => $this->manager->id, 'asset_tag' => 'DUP-1']);

        $this->actingAs($this->manager)
            ->post('/assets/registry', ['asset_tag' => 'DUP-1', 'name' => 'Clash'])
            ->assertSessionHasErrors('asset_tag');
    }

    public function test_read_only_cannot_create_an_asset(): void
    {
        $this->actingAs($this->readOnly)->get('/assets/registry')->assertOk();
        $this->actingAs($this->readOnly)
            ->post('/assets/registry', ['asset_tag' => 'NO-1', 'name' => 'Nope'])
            ->assertForbidden();
    }

    public function test_assets_are_organization_scoped(): void
    {
        $otherOrg = Organization::factory()->create();
        $otherUser = User::factory()->create(['organization_id' => $otherOrg->id]);
        $foreign = Asset::factory()->create(['organization_id' => $otherOrg->id, 'created_by' => $otherUser->id]);

        $this->actingAs($this->manager)->get("/assets/registry/{$foreign->id}")->assertForbidden();
    }

    public function test_commission_endpoint_draws_down_stock_and_creates_asset(): void
    {
        $inventory = app(InventoryService::class);
        $warehouse = Warehouse::factory()->create(['organization_id' => $this->org->id, 'created_by' => $this->manager->id]);
        $product = Product::factory()->create(['organization_id' => $this->org->id, 'created_by' => $this->manager->id, 'unit_cost' => 0]);
        $inventory->receive($product, $warehouse, 3, 1200, ['actor_id' => $this->manager->id]);

        $this->actingAs($this->manager)->post('/assets/registry/commission', [
            'inventory_product_id' => $product->id,
            'inventory_warehouse_id' => $warehouse->id,
            'serial_number' => 'SN-9',
            'status' => 'deployed',
        ])->assertRedirect();

        $this->assertSame('2.000', $inventory->stockFor($product, $warehouse)->quantity_on_hand);
        $this->assertDatabaseHas('asset_assets', [
            'inventory_product_id' => $product->id,
            'serial_number' => 'SN-9',
            'organization_id' => $this->org->id,
        ]);
    }

    public function test_transition_endpoint_updates_status(): void
    {
        $asset = Asset::factory()->create(['organization_id' => $this->org->id, 'created_by' => $this->manager->id, 'status' => 'active']);

        $this->actingAs($this->manager)->post("/assets/registry/{$asset->id}/transition", ['status' => 'under_maintenance'])->assertRedirect();

        $this->assertSame('under_maintenance', $asset->fresh()->status->value);
    }

    public function test_read_only_cannot_commission(): void
    {
        $warehouse = Warehouse::factory()->create(['organization_id' => $this->org->id, 'created_by' => $this->manager->id]);
        $product = Product::factory()->create(['organization_id' => $this->org->id, 'created_by' => $this->manager->id]);

        $this->actingAs($this->readOnly)->post('/assets/registry/commission', [
            'inventory_product_id' => $product->id,
            'inventory_warehouse_id' => $warehouse->id,
        ])->assertForbidden();
    }
}
