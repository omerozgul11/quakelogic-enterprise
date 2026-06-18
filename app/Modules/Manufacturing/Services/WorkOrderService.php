<?php

namespace App\Modules\Manufacturing\Services;

use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Manufacturing\Enums\WorkOrderStatus;
use App\Modules\Manufacturing\Models\WorkOrder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Owns the work-order lifecycle and the build. Completing a work order is the
 * Manufacturing↔Inventory integration: each BOM component is issued from the
 * warehouse (InventoryService->issue) and the finished good is received back in
 * (InventoryService->receive) at rolled-up build cost — all in one transaction,
 * so a component shortage rolls the whole build back.
 */
class WorkOrderService
{
    public function __construct(private readonly InventoryService $inventory) {}

    public function release(WorkOrder $wo): WorkOrder
    {
        $wo->forceFill(['status' => WorkOrderStatus::Released])->save();

        return $wo;
    }

    public function start(WorkOrder $wo): WorkOrder
    {
        $wo->forceFill(['status' => WorkOrderStatus::InProgress, 'started_at' => $wo->started_at ?? now()])->save();

        return $wo;
    }

    public function cancel(WorkOrder $wo): WorkOrder
    {
        if (! $wo->status->isOpen()) {
            throw new RuntimeException('A completed or cancelled work order cannot be cancelled.');
        }

        $wo->forceFill(['status' => WorkOrderStatus::Cancelled])->save();

        return $wo;
    }

    /**
     * Build the work order: consume BOM components and produce finished goods.
     * Defaults to producing the full planned quantity.
     */
    public function complete(WorkOrder $wo, ?float $quantity = null, array $opts = []): WorkOrder
    {
        return DB::transaction(function () use ($wo, $quantity, $opts) {
            $wo = WorkOrder::whereKey($wo->id)->lockForUpdate()->first();

            if (! $wo->status->canComplete()) {
                throw new RuntimeException("Work order {$wo->number} cannot be completed from its current state.");
            }

            $produce = $quantity !== null ? (float) $quantity : (float) $wo->quantity_planned;
            if ($produce <= 0) {
                throw new RuntimeException('Quantity to produce must be greater than zero.');
            }

            // Relations resolved with explicit queries (lazy loading is disabled).
            $bom = $wo->bom()->first();
            if (! $bom) {
                throw new RuntimeException('This work order has no bill of materials to consume.');
            }

            $warehouse = $wo->warehouse()->first();
            $output = $wo->product()->first();
            $actor = $opts['actor_id'] ?? auth()->id();
            $outputQty = (float) $bom->output_quantity > 0 ? (float) $bom->output_quantity : 1.0;
            $factor = $produce / $outputQty;

            $buildCost = 0.0;
            foreach ($bom->items()->get() as $item) {
                $required = (float) $item->quantity_per * $factor;
                if ($required <= 0) {
                    continue;
                }
                $component = $item->product()->first();
                $movement = $this->inventory->issue($component, $warehouse, $required, [
                    'actor_id' => $actor,
                    'reference_type' => 'manufacturing_work_order',
                    'reference_id' => (string) $wo->id,
                    'note' => "WO {$wo->number} — consume {$component->sku}",
                ]);
                $buildCost += $required * (float) $movement->unit_cost;
            }

            $unitCost = $produce > 0 ? $buildCost / $produce : 0.0;
            $this->inventory->receive($output, $warehouse, $produce, $unitCost, [
                'actor_id' => $actor,
                'reference_type' => 'manufacturing_work_order',
                'reference_id' => (string) $wo->id,
                'note' => "WO {$wo->number} — produce {$output->sku}",
            ]);

            $wo->forceFill([
                'status' => WorkOrderStatus::Completed,
                'quantity_produced' => (float) $wo->quantity_produced + $produce,
                'build_cost' => (float) $wo->build_cost + $buildCost,
                'started_at' => $wo->started_at ?? now(),
                'completed_at' => now(),
            ])->save();

            return $wo->fresh();
        });
    }

    /**
     * Component requirements for the planned quantity, with current on-hand and
     * a sufficiency flag — drives the "can we build this?" view.
     *
     * @return array<int,array<string,mixed>>
     */
    public function requirements(WorkOrder $wo): array
    {
        $bom = $wo->bom()->first();
        if (! $bom) {
            return [];
        }

        $warehouse = $wo->warehouse()->first();
        $outputQty = (float) $bom->output_quantity > 0 ? (float) $bom->output_quantity : 1.0;
        $factor = (float) $wo->quantity_planned / $outputQty;

        $rows = [];
        foreach ($bom->items()->orderBy('position')->get() as $item) {
            $component = $item->product()->first();
            if (! $component) {
                continue;
            }
            $required = round((float) $item->quantity_per * $factor, 3);
            $stock = $this->inventory->stockFor($component, $warehouse);
            $available = $stock ? (float) $stock->quantity_on_hand : 0.0;

            $rows[] = [
                'product_id' => $component->id,
                'sku' => $component->sku,
                'name' => $component->name,
                'unit_of_measure' => $component->unit_of_measure,
                'quantity_per' => (float) $item->quantity_per,
                'required' => $required,
                'available' => $available,
                'sufficient' => $available >= $required,
            ];
        }

        return $rows;
    }
}
