<?php

namespace App\Modules\Inventory\Services;

use App\Modules\Inventory\Enums\MovementType;
use App\Modules\Inventory\Exceptions\InsufficientStockException;
use App\Modules\Inventory\Models\Movement;
use App\Modules\Inventory\Models\Product;
use App\Modules\Inventory\Models\Stock;
use App\Modules\Inventory\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The single owner of all stock mutation. Every change to on-hand goes through
 * applyMovement(), which (1) locks the stock row, (2) recomputes the balance and
 * weighted-average cost, and (3) writes an append-only ledger entry — all inside
 * one transaction. Controllers never touch inventory_stocks directly.
 */
class InventoryService
{
    /**
     * Weighted-average cost after receiving `inQty` at `inCost`. Pure (no DB) so
     * it is unit-testable in isolation — mirrors CommissionCalculationService.
     */
    public static function weightedAverageCost(float $onHand, float $currentAvg, float $inQty, float $inCost): float
    {
        // No quantity actually added → the average is unchanged.
        if ($inQty <= 0) {
            return round($currentAvg, 4);
        }

        $newOnHand = $onHand + $inQty;

        // Receiving into a zero/negative balance: nothing to average against, so
        // the incoming cost (if any) becomes the new average.
        if ($newOnHand <= 0) {
            return round($inCost > 0 ? $inCost : $currentAvg, 4);
        }

        return round((($onHand * $currentAvg) + ($inQty * $inCost)) / $newOnHand, 4);
    }

    /** Receive stock into a warehouse at a known unit cost (updates avg cost). */
    public function receive(Product $product, Warehouse $warehouse, float $quantity, float $unitCost = 0, array $opts = []): Movement
    {
        return $this->applyMovement($product, $warehouse, MovementType::Receipt, abs($quantity), $unitCost, $opts);
    }

    /** Issue/consume stock out of a warehouse (e.g. shipped, used in a build). */
    public function issue(Product $product, Warehouse $warehouse, float $quantity, array $opts = []): Movement
    {
        return $this->applyMovement($product, $warehouse, MovementType::Issue, -abs($quantity), null, $opts);
    }

    /** Manual adjustment by a signed delta (+ adds, − removes). */
    public function adjust(Product $product, Warehouse $warehouse, float $delta, array $opts = []): Movement
    {
        $unitCost = $delta > 0 ? ($opts['unit_cost'] ?? null) : null;

        return $this->applyMovement($product, $warehouse, MovementType::Adjustment, $delta, $unitCost, $opts);
    }

    /** Reconcile on-hand to a counted figure (cycle count). */
    public function count(Product $product, Warehouse $warehouse, float $countedQuantity, array $opts = []): Movement
    {
        $current = (float) $this->stockFor($product, $warehouse)?->quantity_on_hand;
        $delta = $countedQuantity - $current;

        return $this->applyMovement($product, $warehouse, MovementType::Count, $delta, null, $opts);
    }

    /**
     * Move stock between warehouses as a linked out+in pair carrying the source's
     * average cost, so valuation is preserved across the transfer.
     *
     * @return array{out:Movement,in:Movement}
     */
    public function transfer(Product $product, Warehouse $from, Warehouse $to, float $quantity, array $opts = []): array
    {
        $quantity = abs($quantity);

        return DB::transaction(function () use ($product, $from, $to, $quantity, $opts) {
            $group = (string) Str::ulid();
            $sourceAvg = (float) ($this->lockedStock($product, $from)?->average_cost ?? 0);
            $meta = ['transfer_group' => $group] + $opts;

            $out = $this->applyMovement($product, $from, MovementType::TransferOut, -$quantity, null, $meta + [
                'note' => $opts['note'] ?? "Transfer to {$to->name}",
            ]);
            $in = $this->applyMovement($product, $to, MovementType::TransferIn, $quantity, $sourceAvg, $meta + [
                'note' => $opts['note'] ?? "Transfer from {$from->name}",
            ]);

            return ['out' => $out, 'in' => $in];
        });
    }

    /** Current stock row for a (product, warehouse), or null if none yet. */
    public function stockFor(Product $product, Warehouse $warehouse): ?Stock
    {
        return Stock::query()
            ->where('inventory_product_id', $product->id)
            ->where('inventory_warehouse_id', $warehouse->id)
            ->first();
    }

    /**
     * The one place stock changes. Locks (or creates) the stock row, applies a
     * signed quantity, recomputes weighted-average cost, persists the new balance
     * and writes the ledger entry. Throws if it would drive on-hand negative
     * (unless opts['allow_negative'] is set).
     */
    private function applyMovement(Product $product, Warehouse $warehouse, MovementType $type, float $signedQuantity, ?float $unitCost, array $opts): Movement
    {
        return DB::transaction(function () use ($product, $warehouse, $type, $signedQuantity, $unitCost, $opts) {
            $stock = $this->lockedStock($product, $warehouse) ?? Stock::create([
                'organization_id' => $product->organization_id,
                'inventory_product_id' => $product->id,
                'inventory_warehouse_id' => $warehouse->id,
                'quantity_on_hand' => 0,
                'quantity_reserved' => 0,
                'average_cost' => (float) $product->unit_cost,
            ]);

            $onHand = (float) $stock->quantity_on_hand;
            $newOnHand = $onHand + $signedQuantity;

            if ($newOnHand < 0 && empty($opts['allow_negative'])) {
                throw InsufficientStockException::for($product->sku, $onHand, abs($signedQuantity));
            }

            $newAvg = ($signedQuantity > 0 && $unitCost !== null)
                ? self::weightedAverageCost($onHand, (float) $stock->average_cost, $signedQuantity, $unitCost)
                : (float) $stock->average_cost;

            $stock->update([
                'quantity_on_hand' => $newOnHand,
                'average_cost' => $newAvg,
            ]);

            return Movement::create([
                'organization_id' => $product->organization_id,
                'created_by' => $opts['actor_id'] ?? auth()->id(),
                'inventory_product_id' => $product->id,
                'inventory_warehouse_id' => $warehouse->id,
                'inventory_location_id' => $opts['location_id'] ?? null,
                'type' => $type,
                'quantity' => $signedQuantity,
                'unit_cost' => $unitCost ?? $newAvg,
                'quantity_after' => $newOnHand,
                'reference_type' => $opts['reference_type'] ?? null,
                'reference_id' => $opts['reference_id'] ?? null,
                'transfer_group' => $opts['transfer_group'] ?? null,
                'note' => $opts['note'] ?? null,
                'occurred_at' => $opts['occurred_at'] ?? now(),
            ]);
        });
    }

    private function lockedStock(Product $product, Warehouse $warehouse): ?Stock
    {
        return Stock::query()
            ->where('inventory_product_id', $product->id)
            ->where('inventory_warehouse_id', $warehouse->id)
            ->lockForUpdate()
            ->first();
    }
}
