<?php

namespace Tests\Feature\Inventory;

use App\Models\Organization;
use App\Models\User;
use App\Modules\Inventory\Exceptions\InsufficientStockException;
use App\Modules\Inventory\Models\Product;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryStockTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $user;
    private Product $product;
    private Warehouse $main;
    private Warehouse $field;
    private InventoryService $inventory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = Organization::factory()->create();
        $this->user = User::factory()->create(['organization_id' => $this->org->id]);
        $this->product = Product::factory()->create([
            'organization_id' => $this->org->id, 'created_by' => $this->user->id, 'unit_cost' => 0,
        ]);
        $this->main = Warehouse::factory()->create(['organization_id' => $this->org->id, 'created_by' => $this->user->id]);
        $this->field = Warehouse::factory()->create(['organization_id' => $this->org->id, 'created_by' => $this->user->id]);
        $this->inventory = app(InventoryService::class);
    }

    public function test_receiving_increases_on_hand_and_writes_a_movement(): void
    {
        $this->inventory->receive($this->product, $this->main, 25, 10, ['actor_id' => $this->user->id]);

        $stock = $this->inventory->stockFor($this->product, $this->main);
        $this->assertSame('25.000', $stock->quantity_on_hand);
        $this->assertSame('10.0000', $stock->average_cost);

        $this->assertDatabaseHas('inventory_movements', [
            'inventory_product_id' => $this->product->id,
            'inventory_warehouse_id' => $this->main->id,
            'type' => 'receipt',
            'quantity' => '25.000',
            'quantity_after' => '25.000',
        ]);
    }

    public function test_two_receipts_blend_to_weighted_average_cost(): void
    {
        $this->inventory->receive($this->product, $this->main, 10, 2, ['actor_id' => $this->user->id]);
        $this->inventory->receive($this->product, $this->main, 10, 4, ['actor_id' => $this->user->id]);

        $stock = $this->inventory->stockFor($this->product, $this->main);
        $this->assertSame('20.000', $stock->quantity_on_hand);
        $this->assertSame('3.0000', $stock->average_cost);
    }

    public function test_issue_reduces_stock_and_guards_against_oversell(): void
    {
        $this->inventory->receive($this->product, $this->main, 5, 1, ['actor_id' => $this->user->id]);
        $this->inventory->issue($this->product, $this->main, 3, ['actor_id' => $this->user->id]);

        $this->assertSame('2.000', $this->inventory->stockFor($this->product, $this->main)->quantity_on_hand);

        $this->expectException(InsufficientStockException::class);
        $this->inventory->issue($this->product, $this->main, 99, ['actor_id' => $this->user->id]);
    }

    public function test_count_reconciles_on_hand_to_the_counted_figure(): void
    {
        $this->inventory->receive($this->product, $this->main, 10, 1, ['actor_id' => $this->user->id]);
        $this->inventory->count($this->product, $this->main, 7, ['actor_id' => $this->user->id]);

        $this->assertSame('7.000', $this->inventory->stockFor($this->product, $this->main)->quantity_on_hand);
        $this->assertDatabaseHas('inventory_movements', ['type' => 'count', 'quantity' => '-3.000']);
    }

    public function test_transfer_moves_stock_and_preserves_cost(): void
    {
        $this->inventory->receive($this->product, $this->main, 20, 6, ['actor_id' => $this->user->id]);
        $this->inventory->transfer($this->product, $this->main, $this->field, 8, ['actor_id' => $this->user->id]);

        $this->assertSame('12.000', $this->inventory->stockFor($this->product, $this->main)->quantity_on_hand);

        $dest = $this->inventory->stockFor($this->product, $this->field);
        $this->assertSame('8.000', $dest->quantity_on_hand);
        $this->assertSame('6.0000', $dest->average_cost);

        $this->assertDatabaseHas('inventory_movements', ['type' => 'transfer_out', 'quantity' => '-8.000']);
        $this->assertDatabaseHas('inventory_movements', ['type' => 'transfer_in', 'quantity' => '8.000']);
    }

    public function test_transfer_cannot_oversell_the_source(): void
    {
        $this->inventory->receive($this->product, $this->main, 5, 6, ['actor_id' => $this->user->id]);

        $this->expectException(InsufficientStockException::class);
        $this->inventory->transfer($this->product, $this->main, $this->field, 10, ['actor_id' => $this->user->id]);
    }
}
