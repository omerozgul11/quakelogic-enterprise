<?php

namespace App\Modules\AssetManagement\Services;

use App\Modules\AssetManagement\Enums\AssetStatus;
use App\Modules\AssetManagement\Models\Asset;
use App\Modules\AssetManagement\Models\MaintenanceRecord;
use App\Modules\Inventory\Models\Product;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Services\InventoryService;
use Illuminate\Support\Facades\DB;

/**
 * Asset lifecycle + the Inventory→Asset bridge. Commissioning issues one unit of
 * a product out of stock (InventoryService->issue) and registers it as a tracked
 * asset, carrying the inventory cost as the asset's purchase cost. Per the doc:
 * an inventory item becomes an asset only when assigned / deployed / commissioned.
 */
class AssetService
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly AssetTagService $tags,
    ) {}

    /**
     * Commission one unit of an inventory product into a tracked asset. Throws
     * InsufficientStockException (rolling back) if there is no unit to draw down.
     */
    public function commissionFromInventory(Product $product, Warehouse $warehouse, int $actorId, array $data = []): Asset
    {
        return DB::transaction(function () use ($product, $warehouse, $actorId, $data) {
            $movement = $this->inventory->issue($product, $warehouse, 1, [
                'actor_id' => $actorId,
                'reference_type' => 'asset_commission',
                'note' => $data['note'] ?? "Commissioned asset from {$product->sku}",
            ]);

            $status = isset($data['status']) ? AssetStatus::from($data['status']) : AssetStatus::Deployed;

            return Asset::create([
                'organization_id' => $product->organization_id,
                'created_by' => $actorId,
                'inventory_product_id' => $product->id,
                'company_id' => $data['company_id'] ?? null,
                'assigned_to' => $data['assigned_to'] ?? null,
                'asset_tag' => $this->tags->generate($product->organization_id),
                'name' => $data['name'] ?? $product->name,
                'serial_number' => $data['serial_number'] ?? null,
                'status' => $status,
                'category' => $data['category'] ?? $product->category,
                'location' => $data['location'] ?? null,
                'condition' => $data['condition'] ?? 'new',
                'purchase_cost' => (float) $movement->unit_cost,
                'current_value' => (float) $movement->unit_cost,
                'currency' => $product->currency,
                'purchased_at' => now()->toDateString(),
                'deployed_at' => $status === AssetStatus::Deployed ? now()->toDateString() : null,
                'notes' => $data['notes'] ?? null,
            ]);
        });
    }

    /** Move an asset to a new lifecycle status, stamping the relevant date. */
    public function transition(Asset $asset, AssetStatus $status): Asset
    {
        $changes = ['status' => $status];

        if ($status === AssetStatus::Deployed && $asset->deployed_at === null) {
            $changes['deployed_at'] = now()->toDateString();
        }
        if ($status->isTerminal() && $asset->retired_at === null) {
            $changes['retired_at'] = now()->toDateString();
        }

        $asset->update($changes);

        return $asset;
    }

    /** Record a maintenance event against an asset. */
    public function logMaintenance(Asset $asset, array $data, ?int $performedBy = null): MaintenanceRecord
    {
        return $asset->maintenanceRecords()->create([
            'organization_id' => $asset->organization_id,
            'performed_by' => $performedBy,
            'type' => $data['type'],
            'status' => $data['status'] ?? 'completed',
            'description' => $data['description'],
            'cost' => $data['cost'] ?? null,
            'performed_at' => $data['performed_at'] ?? now()->toDateString(),
            'next_due_at' => $data['next_due_at'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);
    }
}
