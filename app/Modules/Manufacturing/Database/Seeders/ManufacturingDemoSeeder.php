<?php

namespace App\Modules\Manufacturing\Database\Seeders;

use App\Models\Organization;
use App\Models\User;
use App\Modules\Inventory\Models\Product;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Manufacturing\Enums\BomStatus;
use App\Modules\Manufacturing\Enums\WorkOrderStatus;
use App\Modules\Manufacturing\Models\Bom;
use App\Modules\Manufacturing\Models\WorkOrder;
use App\Modules\Manufacturing\Services\ManufacturingNumberService;
use Illuminate\Database\Seeder;

/**
 * Optional demo data for Manufacturing — one BOM (a finished good built from a
 * couple of components) and a released work order. NOT wired into
 * DatabaseSeeder. Needs Inventory demo products to exist; invoke explicitly:
 *
 *   php artisan db:seed --class="App\Modules\Manufacturing\Database\Seeders\ManufacturingDemoSeeder"
 */
class ManufacturingDemoSeeder extends Seeder
{
    public function run(): void
    {
        $org = Organization::query()->orderBy('id')->first();
        $user = $org ? User::where('organization_id', $org->id)->orderBy('id')->first() : null;

        if (! $org || ! $user) {
            $this->command?->warn('ManufacturingDemoSeeder: no organization/user found — skipping.');

            return;
        }

        $output = Product::where('organization_id', $org->id)->where('type', 'finished_good')->orderBy('id')->first()
            ?? Product::where('organization_id', $org->id)->orderBy('id')->first();
        $components = Product::where('organization_id', $org->id)
            ->when($output, fn ($q) => $q->where('id', '!=', $output->id))
            ->whereIn('type', ['component', 'raw_material'])
            ->orderBy('id')->limit(3)->get();

        if (! $output || $components->count() < 1) {
            $this->command?->warn('ManufacturingDemoSeeder: need Inventory products first (run InventoryDemoSeeder) — skipping.');

            return;
        }

        $bom = Bom::firstOrCreate(
            ['organization_id' => $org->id, 'inventory_product_id' => $output->id, 'version' => 'v1'],
            ['created_by' => $user->id, 'name' => $output->name.' Assembly', 'status' => BomStatus::Active->value, 'output_quantity' => 1, 'is_default' => true],
        );

        if ($bom->items()->count() === 0) {
            foreach ($components as $i => $component) {
                $bom->items()->create([
                    'organization_id' => $org->id,
                    'inventory_product_id' => $component->id,
                    'quantity_per' => $i + 1,
                    'position' => $i,
                ]);
            }
        }

        $warehouse = Warehouse::where('organization_id', $org->id)->orderByDesc('is_default')->first();
        if ($warehouse && ! WorkOrder::where('organization_id', $org->id)->where('manufacturing_bom_id', $bom->id)->exists()) {
            WorkOrder::create([
                'organization_id' => $org->id,
                'created_by' => $user->id,
                'inventory_product_id' => $output->id,
                'manufacturing_bom_id' => $bom->id,
                'inventory_warehouse_id' => $warehouse->id,
                'number' => app(ManufacturingNumberService::class)->generate($org->id),
                'status' => WorkOrderStatus::Released,
                'quantity_planned' => 5,
                'scheduled_date' => now()->toDateString(),
            ]);
        }

        $this->command?->info("ManufacturingDemoSeeder: seeded a BOM ({$bom->items()->count()} components) + work order for \"{$output->name}\".");
    }
}
