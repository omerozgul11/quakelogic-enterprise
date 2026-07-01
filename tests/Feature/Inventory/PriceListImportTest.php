<?php

namespace Tests\Feature\Inventory;

use App\Models\Organization;
use App\Models\User;
use App\Modules\Inventory\Models\Product;
use App\Modules\Inventory\Services\ProductImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Vendor price-list import: reseller price → our cost (converted to USD), sale
 * price = cost + margin%, sheet categories carried onto the product, SKU-upsert.
 */
class PriceListImportTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->org = Organization::factory()->create();
        $this->user = User::factory()->create(['organization_id' => $this->org->id, 'is_active' => true]);
    }

    /** @return array<int,array<int,string>> */
    private function rows(): array
    {
        return [
            ['Part Number', 'Description', 'Category', 'Reseller Price', 'Currency'],
            ['SP-100', 'Triaxial Seismic Sensor', 'Sensors', '100', 'EUR'],
            ['SP-200', 'Data Acquisition Logger', 'Loggers', '200', ''],   // blank → source currency (EUR)
            ['SP-300', 'USB Cable', 'Accessories', '50', 'USD'],           // already USD → no conversion
        ];
    }

    private function service(): ProductImportService
    {
        return app(ProductImportService::class);
    }

    public function test_reseller_becomes_cost_and_sale_is_cost_plus_margin_in_usd(): void
    {
        $r = $this->service()->importPriceList($this->rows(), $this->user, ['margin' => 50, 'from' => 'EUR', 'rate' => 1.10]);

        $this->assertSame(3, $r['created']);

        $eur = Product::where('organization_id', $this->org->id)->where('sku', 'SP-100')->firstOrFail();
        $this->assertEqualsWithDelta(110.0, (float) $eur->unit_cost, 0.001);   // 100 EUR × 1.10
        $this->assertEqualsWithDelta(165.0, (float) $eur->unit_price, 0.001);  // cost × 1.5
        $this->assertSame('USD', $eur->currency);
        $this->assertSame('Sensors', $eur->category);
        $this->assertEqualsWithDelta(100.0, (float) ($eur->metadata['Reseller Price'] ?? 0), 0.001);
        $this->assertSame('EUR', $eur->metadata['Reseller Currency'] ?? null);

        $blank = Product::where('sku', 'SP-200')->firstOrFail();
        $this->assertEqualsWithDelta(220.0, (float) $blank->unit_cost, 0.001);
        $this->assertEqualsWithDelta(330.0, (float) $blank->unit_price, 0.001);
        $this->assertSame('Loggers', $blank->category);

        // A row already in USD is not converted.
        $usd = Product::where('sku', 'SP-300')->firstOrFail();
        $this->assertEqualsWithDelta(50.0, (float) $usd->unit_cost, 0.001);
        $this->assertEqualsWithDelta(75.0, (float) $usd->unit_price, 0.001);
    }

    public function test_reimport_updates_instead_of_duplicating(): void
    {
        $this->service()->importPriceList($this->rows(), $this->user, ['margin' => 50, 'from' => 'EUR', 'rate' => 1.10]);
        $second = $this->service()->importPriceList($this->rows(), $this->user, ['margin' => 50, 'from' => 'EUR', 'rate' => 1.20]);

        $this->assertSame(0, $second['created']);
        $this->assertSame(3, $second['updated']);
        $this->assertSame(3, Product::where('organization_id', $this->org->id)->count());

        // Re-priced at the new rate.
        $this->assertEqualsWithDelta(120.0, (float) Product::where('sku', 'SP-100')->value('unit_cost'), 0.001);
    }

    public function test_missing_reseller_column_is_reported(): void
    {
        $rows = [['Part Number', 'Name'], ['X-1', 'Widget']];
        $r = $this->service()->importPriceList($rows, $this->user, ['from' => 'EUR', 'rate' => 1.1]);
        $this->assertSame(0, $r['created']);
        $this->assertNotEmpty($r['errors']);
    }

    /**
     * Mirrors the real SARA/SPPL sheet: no Category column, instead all-caps
     * section-header rows group the products beneath them, and the "ORDER CODE"
     * column is the SKU. Notes (non-heading priceless rows) are skipped.
     */
    public function test_section_headers_become_categories_and_order_code_is_sku(): void
    {
        $rows = [
            ['DESCRIPTION', 'ORDER CODE', 'Reseller\'s price'],
            ['DIGITAL ARRAY SYSTEM MODULES', null, null],          // section header → category
            ['Digital-Array power booster', 'DA-BST', '189'],
            ['Digital-Array cable extension', 'DA-CABLE', '5'],
            ['DOREMI SEISMOGRAPHS', null, null],                   // next section
            ['Notice: geophones cost extra if bought separately', null, null], // note → skipped, not a category
            ['Doremi 24-channel', 'DOREMI-24', '1000'],
        ];

        $r = $this->service()->importPriceList($rows, $this->user, ['margin' => 50, 'from' => 'EUR', 'rate' => 1.10]);

        $this->assertSame(3, $r['created']);
        $this->assertSame(1, $r['skipped']);   // only the Notice row

        $booster = Product::where('organization_id', $this->org->id)->where('sku', 'DA-BST')->firstOrFail();
        $this->assertSame('Digital Array System Modules', $booster->category);   // tidied from the header
        $this->assertSame('Digital-Array power booster', $booster->name);
        $this->assertEqualsWithDelta(207.9, (float) $booster->unit_cost, 0.001); // 189 × 1.10

        // The product after the note still inherits the DOREMI section (note is not a category).
        $doremi = Product::where('sku', 'DOREMI-24')->firstOrFail();
        $this->assertSame('Doremi Seismographs', $doremi->category);
    }
}
