<?php

namespace App\Modules\AssetManagement\Database\Seeders;

use App\Models\Organization;
use App\Models\User;
use App\Modules\AssetManagement\Enums\MaintenanceType;
use App\Modules\AssetManagement\Models\Asset;
use App\Modules\AssetManagement\Services\AssetService;
use App\Modules\Inventory\Models\Product;
use App\Modules\Inventory\Models\Warehouse;
use Illuminate\Database\Seeder;

/**
 * Optional demo data for Asset Management — commissions a couple of assets out of
 * Inventory stock and logs a calibration record. NOT wired into DatabaseSeeder;
 * needs Inventory demo stock. Invoke explicitly:
 *
 *   php artisan db:seed --class="App\Modules\AssetManagement\Database\Seeders\AssetDemoSeeder"
 */
class AssetDemoSeeder extends Seeder
{
    public function run(): void
    {
        $org = Organization::query()->orderBy('id')->first();
        $user = $org ? User::where('organization_id', $org->id)->orderBy('id')->first() : null;

        if (! $org || ! $user) {
            $this->command?->warn('AssetDemoSeeder: no organization/user found — skipping.');

            return;
        }

        if (Asset::where('organization_id', $org->id)->exists()) {
            $this->command?->info('AssetDemoSeeder: assets already exist — skipping.');

            return;
        }

        $warehouse = Warehouse::where('organization_id', $org->id)->orderByDesc('is_default')->first();
        $service = app(AssetService::class);
        $commissioned = 0;

        if ($warehouse) {
            $products = Product::where('organization_id', $org->id)
                ->where('type', 'finished_good')->orderBy('id')->limit(2)->get();

            foreach ($products as $i => $product) {
                $stock = $product->stocks()->where('inventory_warehouse_id', $warehouse->id)->first();
                if (! $stock || (float) $stock->quantity_on_hand < 1) {
                    continue;
                }
                $asset = $service->commissionFromInventory($product, $warehouse, $user->id, [
                    'serial_number' => 'SN-'.str_pad((string) ($i + 1001), 5, '0', STR_PAD_LEFT),
                    'status' => 'deployed',
                    'location' => $i === 0 ? 'UC Berkeley Seismology Lab' : 'Caltech Field Station',
                ]);
                $service->logMaintenance($asset, [
                    'type' => MaintenanceType::Calibration->value,
                    'description' => 'Initial NIST-traceable calibration',
                    'cost' => 650,
                    'next_due_at' => now()->addYear()->toDateString(),
                ], $user->id);
                $commissioned++;
            }
        }

        $this->command?->info("AssetDemoSeeder: commissioned {$commissioned} asset(s) from inventory for \"{$org->name}\".");
    }
}
