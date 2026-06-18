<?php

namespace App\Modules\Inventory\Database\Seeders;

use App\Models\Organization;
use App\Models\User;
use App\Modules\Inventory\Models\Product;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Services\InventoryService;
use Illuminate\Database\Seeder;

/**
 * Optional demo data for the Inventory module — QuakeLogic-flavoured products,
 * two warehouses and some opening stock. NOT wired into DatabaseSeeder, so it
 * never runs against production automatically. Invoke explicitly:
 *
 *   php artisan db:seed --class="App\Modules\Inventory\Database\Seeders\InventoryDemoSeeder"
 *
 * Idempotent: products/warehouses are firstOrCreate'd; opening stock is only
 * seeded for a product the first time it is created.
 */
class InventoryDemoSeeder extends Seeder
{
    public function run(): void
    {
        $org = Organization::query()->orderBy('id')->first();
        $user = $org ? User::where('organization_id', $org->id)->orderBy('id')->first() : null;

        if (! $org || ! $user) {
            $this->command?->warn('InventoryDemoSeeder: no organization/user found — skipping.');

            return;
        }

        $inventory = app(InventoryService::class);

        $warehouses = collect([
            ['code' => 'MAIN', 'name' => 'Main Warehouse', 'type' => 'main', 'city' => 'Sacramento', 'state' => 'CA', 'is_default' => true],
            ['code' => 'PROD', 'name' => 'Production Floor', 'type' => 'main', 'city' => 'Sacramento', 'state' => 'CA'],
        ])->mapWithKeys(function (array $attrs) use ($org, $user) {
            $wh = Warehouse::firstOrCreate(
                ['organization_id' => $org->id, 'code' => $attrs['code']],
                [...$attrs, 'created_by' => $user->id],
            );

            return [$attrs['code'] => $wh];
        });

        $catalog = [
            ['sku' => 'QL-F330', 'name' => 'F330 Force-Balance Accelerometer', 'type' => 'finished_good', 'category' => 'Sensors', 'cost' => 1850, 'price' => 3200, 'reorder' => 5, 'open' => 12],
            ['sku' => 'QL-CUBE', 'name' => 'CUBE Strong-Motion Digitizer', 'type' => 'finished_good', 'category' => 'Digitizers', 'cost' => 2400, 'price' => 4100, 'reorder' => 4, 'open' => 8],
            ['sku' => 'QL-AIR', 'name' => 'AIR Infrasound Sensor', 'type' => 'finished_good', 'category' => 'Sensors', 'cost' => 1200, 'price' => 2150, 'reorder' => 6, 'open' => 3],
            ['sku' => 'QL-GPS-ANT', 'name' => 'GPS Timing Antenna', 'type' => 'component', 'category' => 'Accessories', 'cost' => 95, 'price' => 180, 'reorder' => 20, 'open' => 40],
            ['sku' => 'QL-CBL-10', 'name' => 'Sensor Cable, 10m', 'type' => 'component', 'category' => 'Cables', 'cost' => 22, 'price' => 55, 'reorder' => 50, 'open' => 120],
            ['sku' => 'QL-ENCL', 'name' => 'Field Enclosure, IP67', 'type' => 'raw_material', 'category' => 'Spare Parts', 'cost' => 140, 'price' => 260, 'reorder' => 10, 'open' => 18],
            ['sku' => 'QL-CAL-SVC', 'name' => 'NIST-Traceable Calibration', 'type' => 'service', 'category' => 'Services', 'cost' => 0, 'price' => 650, 'reorder' => null, 'open' => 0],
        ];

        $main = $warehouses['MAIN'];

        foreach ($catalog as $item) {
            $product = Product::firstOrNew([
                'organization_id' => $org->id,
                'sku' => $item['sku'],
            ]);

            if ($product->exists) {
                continue; // already seeded — don't double-add opening stock
            }

            $product->fill([
                'created_by' => $user->id,
                'owner_id' => $user->id,
                'name' => $item['name'],
                'type' => $item['type'],
                'category' => $item['category'],
                'unit_of_measure' => 'each',
                'unit_cost' => $item['cost'],
                'unit_price' => $item['price'],
                'currency' => 'USD',
                'reorder_point' => $item['reorder'],
                'is_active' => true,
            ])->save();

            if ($item['open'] > 0) {
                $inventory->receive($product, $main, (float) $item['open'], (float) $item['cost'], [
                    'actor_id' => $user->id,
                    'note' => 'Opening balance (demo)',
                ]);
            }
        }

        $this->command?->info('InventoryDemoSeeder: seeded '.count($catalog).' products across '.$warehouses->count().' warehouses for "'.$org->name.'".');
    }
}
