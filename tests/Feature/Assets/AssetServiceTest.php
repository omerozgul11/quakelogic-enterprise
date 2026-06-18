<?php

namespace Tests\Feature\Assets;

use App\Models\Organization;
use App\Models\User;
use App\Modules\AssetManagement\Enums\AssetStatus;
use App\Modules\AssetManagement\Enums\MaintenanceType;
use App\Modules\AssetManagement\Models\Asset;
use App\Modules\AssetManagement\Services\AssetService;
use App\Modules\AssetManagement\Services\AssetTagService;
use App\Modules\Inventory\Exceptions\InsufficientStockException;
use App\Modules\Inventory\Models\Product;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetServiceTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $user;
    private Warehouse $warehouse;
    private Product $product;
    private AssetService $service;
    private InventoryService $inventory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = Organization::factory()->create();
        $this->user = User::factory()->create(['organization_id' => $this->org->id]);
        $this->warehouse = Warehouse::factory()->create(['organization_id' => $this->org->id, 'created_by' => $this->user->id]);
        $this->product = Product::factory()->create(['organization_id' => $this->org->id, 'created_by' => $this->user->id, 'sku' => 'F330', 'unit_cost' => 0]);
        $this->service = app(AssetService::class);
        $this->inventory = app(InventoryService::class);
    }

    public function test_asset_tags_are_sequential(): void
    {
        $tags = app(AssetTagService::class);
        $first = $tags->generate($this->org->id);
        Asset::factory()->create(['organization_id' => $this->org->id, 'created_by' => $this->user->id, 'asset_tag' => $first]);
        $year = now()->year;
        $this->assertSame("AST-{$year}-0001", $first);
        $this->assertSame("AST-{$year}-0002", $tags->generate($this->org->id));
    }

    public function test_commission_draws_down_stock_and_creates_an_asset(): void
    {
        $this->inventory->receive($this->product, $this->warehouse, 5, 1850, ['actor_id' => $this->user->id]);

        $asset = $this->service->commissionFromInventory($this->product, $this->warehouse, $this->user->id, [
            'serial_number' => 'SN-001', 'status' => 'deployed', 'location' => 'UC Berkeley',
        ]);

        $this->assertSame('4.000', $this->inventory->stockFor($this->product, $this->warehouse)->quantity_on_hand);
        $this->assertSame(AssetStatus::Deployed, $asset->status);
        $this->assertSame('1850.00', $asset->purchase_cost);
        $this->assertSame($this->product->id, $asset->inventory_product_id);
        $this->assertNotNull($asset->deployed_at);
        $this->assertDatabaseHas('inventory_movements', ['reference_type' => 'asset_commission', 'type' => 'issue']);
    }

    public function test_commission_without_stock_throws(): void
    {
        $this->expectException(InsufficientStockException::class);
        $this->service->commissionFromInventory($this->product, $this->warehouse, $this->user->id);
    }

    public function test_transition_to_retired_stamps_retired_at(): void
    {
        $asset = Asset::factory()->create(['organization_id' => $this->org->id, 'created_by' => $this->user->id, 'status' => AssetStatus::Active->value]);

        $this->service->transition($asset, AssetStatus::Retired);

        $this->assertSame(AssetStatus::Retired, $asset->fresh()->status);
        $this->assertNotNull($asset->fresh()->retired_at);
    }

    public function test_log_maintenance_creates_a_record(): void
    {
        $asset = Asset::factory()->create(['organization_id' => $this->org->id, 'created_by' => $this->user->id]);

        $this->service->logMaintenance($asset, [
            'type' => MaintenanceType::Calibration->value,
            'description' => 'Annual NIST calibration',
            'cost' => 650,
            'next_due_at' => now()->addYear()->toDateString(),
        ], $this->user->id);

        $this->assertDatabaseHas('asset_maintenance_records', [
            'asset_id' => $asset->id,
            'type' => 'calibration',
            'performed_by' => $this->user->id,
        ]);
    }
}
