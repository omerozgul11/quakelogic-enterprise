<?php

namespace App\Modules\Procurement\Database\Seeders;

use App\Models\Organization;
use App\Models\User;
use App\Modules\Inventory\Models\Product;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Procurement\Enums\PurchaseOrderStatus;
use App\Modules\Procurement\Models\PurchaseOrder;
use App\Modules\Procurement\Models\Supplier;
use App\Modules\Procurement\Services\ProcurementNumberService;
use App\Modules\Procurement\Services\PurchaseOrderService;
use Illuminate\Database\Seeder;

/**
 * Optional demo data for Procurement — a few suppliers and one sample approved
 * purchase order. NOT wired into DatabaseSeeder. Invoke explicitly:
 *
 *   php artisan db:seed --class="App\Modules\Procurement\Database\Seeders\ProcurementDemoSeeder"
 *
 * Idempotent: suppliers are firstOrCreate'd; the sample PO is created once.
 */
class ProcurementDemoSeeder extends Seeder
{
    public function run(): void
    {
        $org = Organization::query()->orderBy('id')->first();
        $user = $org ? User::where('organization_id', $org->id)->orderBy('id')->first() : null;

        if (! $org || ! $user) {
            $this->command?->warn('ProcurementDemoSeeder: no organization/user found — skipping.');

            return;
        }

        $suppliers = [
            ['code' => 'SUP-ACME', 'name' => 'Acme Sensor Components', 'category' => 'Electronics', 'payment_terms' => 'Net 30'],
            ['code' => 'SUP-PRECMACH', 'name' => 'Precision Machining Co.', 'category' => 'Machining', 'payment_terms' => 'Net 45'],
            ['code' => 'SUP-GLOBLOG', 'name' => 'Global Logistics Partners', 'category' => 'Logistics', 'payment_terms' => 'Due on receipt'],
        ];

        $created = [];
        foreach ($suppliers as $attrs) {
            $created[$attrs['code']] = Supplier::firstOrCreate(
                ['organization_id' => $org->id, 'code' => $attrs['code']],
                [...$attrs, 'created_by' => $user->id, 'owner_id' => $user->id, 'status' => 'active', 'currency' => 'USD'],
            );
        }

        // Sample PO against the first supplier, receiving into the default
        // warehouse if Inventory demo data exists.
        $supplier = $created['SUP-ACME'];
        $warehouse = Warehouse::where('organization_id', $org->id)->orderByDesc('is_default')->first();
        $product = Product::where('organization_id', $org->id)->orderBy('id')->first();

        $alreadyHasPo = PurchaseOrder::where('organization_id', $org->id)
            ->where('procurement_supplier_id', $supplier->id)->exists();

        if (! $alreadyHasPo && $warehouse && $product) {
            $service = app(PurchaseOrderService::class);
            $po = PurchaseOrder::create([
                'organization_id' => $org->id,
                'created_by' => $user->id,
                'procurement_supplier_id' => $supplier->id,
                'inventory_warehouse_id' => $warehouse->id,
                'number' => app(ProcurementNumberService::class)->generate($org->id),
                'status' => PurchaseOrderStatus::Draft,
                'order_date' => now()->toDateString(),
                'currency' => 'USD',
                'tax_rate' => 8.25,
            ]);
            $po->items()->create([
                'organization_id' => $org->id,
                'inventory_product_id' => $product->id,
                'description' => $product->name,
                'sku' => $product->sku,
                'quantity_ordered' => 10,
                'unit_cost' => (float) $product->unit_cost ?: 100,
                'position' => 0,
            ]);
            $service->recalcTotals($po);
            $service->approve($po->fresh(), $user->id);

            $this->command?->info("ProcurementDemoSeeder: created sample PO {$po->number} (approved).");
        }

        $this->command?->info('ProcurementDemoSeeder: seeded '.count($suppliers)." suppliers for \"{$org->name}\".");
    }
}
