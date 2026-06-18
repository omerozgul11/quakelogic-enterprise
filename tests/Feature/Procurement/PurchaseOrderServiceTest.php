<?php

namespace Tests\Feature\Procurement;

use App\Models\Organization;
use App\Models\User;
use App\Modules\Inventory\Models\Product;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Procurement\Enums\PurchaseOrderStatus;
use App\Modules\Procurement\Models\PurchaseOrder;
use App\Modules\Procurement\Models\PurchaseOrderItem;
use App\Modules\Procurement\Models\Supplier;
use App\Modules\Procurement\Services\ProcurementNumberService;
use App\Modules\Procurement\Services\PurchaseOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class PurchaseOrderServiceTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $user;
    private Warehouse $warehouse;
    private Product $product;
    private Supplier $supplier;
    private PurchaseOrderService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = Organization::factory()->create();
        $this->user = User::factory()->create(['organization_id' => $this->org->id]);
        $this->warehouse = Warehouse::factory()->create(['organization_id' => $this->org->id, 'created_by' => $this->user->id]);
        $this->product = Product::factory()->create(['organization_id' => $this->org->id, 'created_by' => $this->user->id, 'unit_cost' => 0]);
        $this->supplier = Supplier::factory()->create(['organization_id' => $this->org->id, 'created_by' => $this->user->id]);
        $this->service = app(PurchaseOrderService::class);
    }

    private function makePo(array $lines, array $attrs = []): PurchaseOrder
    {
        $po = PurchaseOrder::factory()->create([
            'organization_id' => $this->org->id,
            'created_by' => $this->user->id,
            'procurement_supplier_id' => $this->supplier->id,
            'inventory_warehouse_id' => $this->warehouse->id,
            ...$attrs,
        ]);
        foreach ($lines as $i => $line) {
            PurchaseOrderItem::create([
                'organization_id' => $this->org->id,
                'procurement_purchase_order_id' => $po->id,
                'inventory_product_id' => $line['product'] ?? $this->product->id,
                'description' => $line['desc'] ?? 'Line',
                'quantity_ordered' => $line['qty'],
                'unit_cost' => $line['cost'],
                'position' => $i,
            ]);
        }

        return $this->service->recalcTotals($po->fresh());
    }

    public function test_po_numbers_are_sequential_per_year(): void
    {
        $numbers = app(ProcurementNumberService::class);
        $first = $numbers->generate($this->org->id);
        PurchaseOrder::factory()->create([
            'organization_id' => $this->org->id, 'created_by' => $this->user->id,
            'procurement_supplier_id' => $this->supplier->id, 'number' => $first,
        ]);
        $second = $numbers->generate($this->org->id);

        $year = now()->year;
        $this->assertSame("PO-{$year}-0001", $first);
        $this->assertSame("PO-{$year}-0002", $second);
    }

    public function test_recalc_totals_applies_tax_and_shipping(): void
    {
        $po = $this->makePo(
            [['qty' => 10, 'cost' => 100], ['qty' => 5, 'cost' => 20]],
            ['tax_rate' => 10, 'shipping_amount' => 50],
        );

        // subtotal 1100, tax 110, + shipping 50 → 1260
        $this->assertSame('1100.00', $po->subtotal);
        $this->assertSame('110.00', $po->tax_amount);
        $this->assertSame('1260.00', $po->total);
    }

    public function test_receiving_posts_to_inventory_and_completes_the_po(): void
    {
        $po = $this->makePo([['qty' => 10, 'cost' => 8]]);
        $this->service->approve($po, $this->user->id);

        $this->service->receiveAll($po->fresh(), ['actor_id' => $this->user->id]);

        $this->assertSame(PurchaseOrderStatus::Received, $po->fresh()->status);
        $this->assertSame('10.000', app(InventoryService::class)->stockFor($this->product, $this->warehouse)->quantity_on_hand);
        $this->assertDatabaseHas('inventory_movements', [
            'reference_type' => 'procurement_purchase_order',
            'reference_id' => (string) $po->id,
            'type' => 'receipt',
        ]);
    }

    public function test_partial_receipt_sets_partially_received(): void
    {
        $po = $this->makePo([['qty' => 10, 'cost' => 8]]);
        $this->service->approve($po, $this->user->id);

        $item = $po->items()->first();
        $this->service->receiveItem($item, 4, ['actor_id' => $this->user->id]);

        $this->assertSame(PurchaseOrderStatus::PartiallyReceived, $po->fresh()->status);
        $this->assertSame('4.000', app(InventoryService::class)->stockFor($this->product, $this->warehouse)->quantity_on_hand);
    }

    public function test_receipt_is_capped_at_outstanding_quantity(): void
    {
        $po = $this->makePo([['qty' => 5, 'cost' => 8]]);
        $this->service->approve($po, $this->user->id);

        // Asking for 50 against an order of 5 only receives 5.
        $this->service->receiveItem($po->items()->first(), 50, ['actor_id' => $this->user->id]);

        $this->assertSame('5.000', $po->items()->first()->quantity_received);
        $this->assertSame('5.000', app(InventoryService::class)->stockFor($this->product, $this->warehouse)->quantity_on_hand);
    }

    public function test_cannot_receive_an_unapproved_po(): void
    {
        $po = $this->makePo([['qty' => 5, 'cost' => 8]]); // still draft

        $this->expectException(RuntimeException::class);
        $this->service->receiveItem($po->items()->first(), 1, ['actor_id' => $this->user->id]);
    }

    public function test_received_po_cannot_be_cancelled(): void
    {
        $po = $this->makePo([['qty' => 5, 'cost' => 8]]);
        $this->service->approve($po, $this->user->id);
        $this->service->receiveAll($po->fresh(), ['actor_id' => $this->user->id]);

        $this->expectException(RuntimeException::class);
        $this->service->cancel($po->fresh());
    }
}
