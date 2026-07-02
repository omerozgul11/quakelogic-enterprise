<?php

namespace Tests\Feature\Procurement;

use App\Models\Organization;
use App\Models\User;
use App\Modules\Inventory\Enums\ProductType;
use App\Modules\Inventory\Models\Product;
use App\Modules\Procurement\Models\Supplier;
use App\Modules\Procurement\Services\SupplierPriceListService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Dropping a supplier price list parses its lines, matches them to existing
 * inventory products, updates the matched products' COST only (never the sale
 * price), links product↔supplier, and creates new products for unmatched lines.
 */
class SupplierPriceListTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = Organization::factory()->create();
        $this->user = User::factory()->create(['organization_id' => $this->org->id, 'is_active' => true, 'email_verified_at' => now()]);
    }

    private function supplier(): Supplier
    {
        return Supplier::create([
            'organization_id' => $this->org->id,
            'created_by' => $this->user->id,
            'owner_id' => $this->user->id,
            'code' => 'SUP-'.random_int(1000, 9999),
            'name' => 'Acme Instruments',
            'status' => 'active',
            'currency' => 'USD',
        ]);
    }

    private function product(string $sku, string $name, float $cost, float $price): Product
    {
        return Product::create([
            'organization_id' => $this->org->id,
            'created_by' => $this->user->id,
            'owner_id' => $this->user->id,
            'sku' => $sku,
            'name' => $name,
            'type' => ProductType::Good->value,
            'unit_cost' => $cost,
            'unit_price' => $price,
            'currency' => 'USD',
        ]);
    }

    public function test_it_updates_cost_only_links_and_creates_new_products(): void
    {
        $supplier = $this->supplier();
        $existing = $this->product('AAA-1', 'Widget A', 10, 25);

        $csv = "SKU,Name,Net Price\nAAA-1,Widget A,88.50\nNEW-1,Fresh Widget,12.00\n";
        $file = UploadedFile::fake()->createWithContent('list.csv', $csv);

        $svc = app(SupplierPriceListService::class);
        $parsed = $svc->parse($file, $supplier);

        $this->assertSame('ok', $parsed['status']);
        $this->assertCount(2, $parsed['lines']);
        $this->assertSame('update', $parsed['lines'][0]['action']);
        $this->assertSame($existing->id, $parsed['lines'][0]['match']['product_id']);
        $this->assertSame('create', $parsed['lines'][1]['action']);
        $this->assertNull($parsed['lines'][1]['match']);

        $lines = array_map(fn ($l) => [
            'action' => $l['action'],
            'product_id' => $l['match']['product_id'] ?? null,
            'supplier_sku' => $l['supplier_sku'],
            'name' => $l['name'],
            'price' => $l['price'],
            'currency' => $l['currency'],
        ], $parsed['lines']);

        $summary = $svc->apply($supplier, $lines, $this->user);

        $this->assertSame(['updated' => 1, 'created' => 1, 'linked' => 2, 'skipped' => 0], $summary);

        $existing->refresh();
        $this->assertEqualsWithDelta(88.50, (float) $existing->unit_cost, 0.001);   // cost updated
        $this->assertEqualsWithDelta(25.0, (float) $existing->unit_price, 0.001);    // sale price untouched

        $created = Product::where('organization_id', $this->org->id)->where('name', 'Fresh Widget')->first();
        $this->assertNotNull($created);
        $this->assertEqualsWithDelta(12.0, (float) $created->unit_cost, 0.001);

        // Both products are linked to the supplier at the supplier's price.
        $this->assertDatabaseHas('procurement_supplier_products', [
            'procurement_supplier_id' => $supplier->id,
            'inventory_product_id' => $existing->id,
            'supplier_price' => 88.5000,
        ]);
        $this->assertSame(2, $supplier->products()->count());
    }

    public function test_re_import_updates_the_existing_link_not_a_duplicate(): void
    {
        $supplier = $this->supplier();
        $this->product('AAA-1', 'Widget A', 10, 25);

        $svc = app(SupplierPriceListService::class);
        foreach ([50.0, 61.0] as $price) {
            $file = UploadedFile::fake()->createWithContent('list.csv', "SKU,Name,Price\nAAA-1,Widget A,{$price}\n");
            $parsed = $svc->parse($file, $supplier);
            $lines = array_map(fn ($l) => [
                'action' => $l['action'],
                'product_id' => $l['match']['product_id'] ?? null,
                'supplier_sku' => $l['supplier_sku'],
                'name' => $l['name'],
                'price' => $l['price'],
                'currency' => $l['currency'],
            ], $parsed['lines']);
            $svc->apply($supplier, $lines, $this->user);
        }

        $this->assertSame(1, $supplier->products()->count());
        $this->assertEqualsWithDelta(61.0, (float) $supplier->products()->first()->supplier_price, 0.001);
    }

    public function test_apply_route_requires_manage_suppliers(): void
    {
        foreach (['access procurement', 'view procurement'] as $p) {
            Permission::findOrCreate($p, 'web');
        }
        $this->user->givePermissionTo(['access procurement', 'view procurement']); // no "manage suppliers"

        $supplier = $this->supplier();

        $this->actingAs($this->user)
            ->post("/procurement/suppliers/{$supplier->id}/price-list/apply", [
                'lines' => [['action' => 'create', 'name' => 'X', 'price' => 5, 'currency' => 'USD']],
            ])
            ->assertForbidden();

        $this->assertSame(0, Product::where('organization_id', $this->org->id)->count());
    }
}
