<?php

namespace Tests\Unit\Inventory;

use App\Modules\Inventory\Services\InventoryService;
use PHPUnit\Framework\TestCase;

/**
 * Pure (no-DB) checks of the weighted-average cost math — the valuation core,
 * mirroring how CommissionCalculationService::computeCommission is unit-tested.
 */
class InventoryCostTest extends TestCase
{
    public function test_blends_two_receipts_into_a_weighted_average(): void
    {
        // 10 @ $2 then 10 @ $4  →  $3 average.
        $this->assertSame(3.0, InventoryService::weightedAverageCost(10, 2, 10, 4));
    }

    public function test_receiving_into_empty_stock_takes_the_incoming_cost(): void
    {
        $this->assertSame(5.0, InventoryService::weightedAverageCost(0, 0, 8, 5));
    }

    public function test_unequal_quantities_weight_toward_the_larger_lot(): void
    {
        // 90 @ $1 + 10 @ $11 = (90 + 110) / 100 = $2.00
        $this->assertSame(2.0, InventoryService::weightedAverageCost(90, 1, 10, 11));
    }

    public function test_non_positive_receipt_keeps_current_average(): void
    {
        $this->assertSame(7.0, InventoryService::weightedAverageCost(5, 7, 0, 99));
    }

    public function test_result_is_rounded_to_four_places(): void
    {
        // (1*1 + 2*2)/3 = 1.6666… → 1.6667
        $this->assertSame(1.6667, InventoryService::weightedAverageCost(1, 1, 2, 2));
    }
}
