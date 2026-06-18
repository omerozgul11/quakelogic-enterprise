<?php

namespace Tests\Feature\Manufacturing;

use App\Models\Organization;
use App\Models\User;
use App\Modules\Inventory\Exceptions\InsufficientStockException;
use App\Modules\Inventory\Models\Product;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Manufacturing\Enums\WorkOrderStatus;
use App\Modules\Manufacturing\Models\Bom;
use App\Modules\Manufacturing\Models\WorkOrder;
use App\Modules\Manufacturing\Services\ManufacturingNumberService;
use App\Modules\Manufacturing\Services\WorkOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class WorkOrderServiceTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $user;
    private Warehouse $warehouse;
    private Product $output;
    private Product $compA;
    private Product $compB;
    private Bom $bom;
    private WorkOrderService $service;
    private InventoryService $inventory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = Organization::factory()->create();
        $this->user = User::factory()->create(['organization_id' => $this->org->id]);
        $this->warehouse = Warehouse::factory()->create(['organization_id' => $this->org->id, 'created_by' => $this->user->id]);
        $this->output = Product::factory()->create(['organization_id' => $this->org->id, 'created_by' => $this->user->id, 'sku' => 'FG', 'unit_cost' => 0]);
        $this->compA = Product::factory()->create(['organization_id' => $this->org->id, 'created_by' => $this->user->id, 'sku' => 'CA', 'unit_cost' => 0]);
        $this->compB = Product::factory()->create(['organization_id' => $this->org->id, 'created_by' => $this->user->id, 'sku' => 'CB', 'unit_cost' => 0]);

        $this->inventory = app(InventoryService::class);
        $this->service = app(WorkOrderService::class);

        // 1 FG = 2× CA + 3× CB
        $this->bom = Bom::factory()->create([
            'organization_id' => $this->org->id, 'created_by' => $this->user->id,
            'inventory_product_id' => $this->output->id, 'output_quantity' => 1,
        ]);
        $this->bom->items()->create(['organization_id' => $this->org->id, 'inventory_product_id' => $this->compA->id, 'quantity_per' => 2, 'position' => 0]);
        $this->bom->items()->create(['organization_id' => $this->org->id, 'inventory_product_id' => $this->compB->id, 'quantity_per' => 3, 'position' => 1]);
    }

    private function stockComponents(): void
    {
        $this->inventory->receive($this->compA, $this->warehouse, 100, 5, ['actor_id' => $this->user->id]);
        $this->inventory->receive($this->compB, $this->warehouse, 100, 2, ['actor_id' => $this->user->id]);
    }

    private function releasedWo(float $qty = 10): WorkOrder
    {
        return WorkOrder::factory()->create([
            'organization_id' => $this->org->id, 'created_by' => $this->user->id,
            'inventory_product_id' => $this->output->id, 'manufacturing_bom_id' => $this->bom->id,
            'inventory_warehouse_id' => $this->warehouse->id, 'quantity_planned' => $qty,
            'status' => WorkOrderStatus::Released->value,
        ]);
    }

    public function test_wo_numbers_are_sequential(): void
    {
        $numbers = app(ManufacturingNumberService::class);
        $first = $numbers->generate($this->org->id);
        WorkOrder::factory()->create([
            'organization_id' => $this->org->id, 'created_by' => $this->user->id,
            'inventory_product_id' => $this->output->id, 'inventory_warehouse_id' => $this->warehouse->id,
            'number' => $first,
        ]);
        $year = now()->year;
        $this->assertSame("WO-{$year}-0001", $first);
        $this->assertSame("WO-{$year}-0002", $numbers->generate($this->org->id));
    }

    public function test_build_consumes_components_and_produces_finished_goods(): void
    {
        $this->stockComponents();
        $wo = $this->releasedWo(10);

        $this->service->complete($wo, null, ['actor_id' => $this->user->id]);

        $this->assertSame(WorkOrderStatus::Completed, $wo->fresh()->status);
        $this->assertSame('80.000', $this->inventory->stockFor($this->compA, $this->warehouse)->quantity_on_hand); // 100 - 2*10
        $this->assertSame('70.000', $this->inventory->stockFor($this->compB, $this->warehouse)->quantity_on_hand); // 100 - 3*10

        $fg = $this->inventory->stockFor($this->output, $this->warehouse);
        $this->assertSame('10.000', $fg->quantity_on_hand);
        $this->assertSame('16.0000', $fg->average_cost); // (2*5 + 3*2) = 16 per unit
    }

    public function test_build_records_rolled_up_cost_and_inventory_reference(): void
    {
        $this->stockComponents();
        $wo = $this->releasedWo(10);

        $this->service->complete($wo, null, ['actor_id' => $this->user->id]);

        $this->assertSame('160.0000', $wo->fresh()->build_cost); // 16 * 10
        $this->assertDatabaseHas('inventory_movements', [
            'reference_type' => 'manufacturing_work_order',
            'reference_id' => (string) $wo->id,
            'type' => 'receipt', // finished goods produced
        ]);
    }

    public function test_insufficient_components_block_the_build_and_roll_back(): void
    {
        // Only enough CA for 2 units, none of CB.
        $this->inventory->receive($this->compA, $this->warehouse, 4, 5, ['actor_id' => $this->user->id]);
        $wo = $this->releasedWo(10);

        try {
            $this->service->complete($wo, null, ['actor_id' => $this->user->id]);
            $this->fail('Expected InsufficientStockException');
        } catch (InsufficientStockException) {
            // expected
        }

        // Nothing consumed or produced; WO still released.
        $this->assertSame('4.000', $this->inventory->stockFor($this->compA, $this->warehouse)->quantity_on_hand);
        $this->assertNull($this->inventory->stockFor($this->output, $this->warehouse));
        $this->assertSame(WorkOrderStatus::Released, $wo->fresh()->status);
    }

    public function test_cannot_complete_a_draft_work_order(): void
    {
        $this->stockComponents();
        $wo = WorkOrder::factory()->create([
            'organization_id' => $this->org->id, 'created_by' => $this->user->id,
            'inventory_product_id' => $this->output->id, 'manufacturing_bom_id' => $this->bom->id,
            'inventory_warehouse_id' => $this->warehouse->id, 'quantity_planned' => 1,
            'status' => WorkOrderStatus::Draft->value,
        ]);

        $this->expectException(RuntimeException::class);
        $this->service->complete($wo, null, ['actor_id' => $this->user->id]);
    }

    public function test_requirements_flags_shortages(): void
    {
        $this->inventory->receive($this->compA, $this->warehouse, 100, 5, ['actor_id' => $this->user->id]); // enough A
        // no B
        $rows = collect($this->service->requirements($this->releasedWo(10)));

        $this->assertTrue($rows->firstWhere('sku', 'CA')['sufficient']);
        $this->assertFalse($rows->firstWhere('sku', 'CB')['sufficient']);
    }
}
