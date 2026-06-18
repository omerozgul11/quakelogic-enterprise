<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Inventory & Warehouse module — the shared Product Master plus stock tracking.
 * Additive only: every table is new and `inventory_`-prefixed; nothing existing
 * is touched. Stock-on-hand is tracked at (product, warehouse) grain; bins
 * (inventory_locations) are a per-warehouse catalog referenced by movements for
 * traceability. Monetary/quantity columns are decimals, never floats.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Product master — shared catalog referenced by future Procurement
        // (supplier pricing), Manufacturing (BOM), Asset, Calibration & Finance.
        Schema::create('inventory_products', function (Blueprint $table) {
            $table->id();
            $table->string('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('sku');
            $table->string('name');
            $table->string('type')->default('good')->index();        // App\Modules\Inventory\Enums\ProductType
            $table->string('category')->nullable()->index();
            $table->text('description')->nullable();
            $table->string('unit_of_measure')->default('each');
            $table->string('barcode')->nullable()->index();
            $table->string('manufacturer')->nullable();
            $table->string('mpn')->nullable();                        // manufacturer part number

            $table->decimal('unit_cost', 18, 4)->default(0);
            $table->decimal('unit_price', 18, 4)->default(0);
            $table->string('currency', 3)->default('USD');

            $table->decimal('reorder_point', 18, 3)->nullable();
            $table->decimal('reorder_quantity', 18, 3)->nullable();
            $table->unsignedInteger('lead_time_days')->nullable();
            $table->decimal('weight', 12, 3)->nullable();

            $table->boolean('is_serialized')->default(false);
            $table->boolean('track_inventory')->default(true);
            $table->boolean('is_active')->default(true)->index();
            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'sku']);
            $table->index(['organization_id', 'is_active']);
        });

        // Warehouses — physical or virtual stock-holding locations.
        Schema::create('inventory_warehouses', function (Blueprint $table) {
            $table->id();
            $table->string('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users');

            $table->string('code');
            $table->string('name');
            $table->string('type')->default('main');                 // main | transit | supplier | customer | virtual
            $table->string('address_line1')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('country')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'code']);
        });

        // Bin / location catalog within a warehouse (Zone→Aisle→Rack→Shelf→Bin).
        Schema::create('inventory_locations', function (Blueprint $table) {
            $table->id();
            $table->string('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_warehouse_id')->constrained('inventory_warehouses')->cascadeOnDelete();

            $table->string('code');
            $table->string('name')->nullable();
            $table->string('zone')->nullable();
            $table->string('aisle')->nullable();
            $table->string('rack')->nullable();
            $table->string('shelf')->nullable();
            $table->string('bin')->nullable();
            $table->string('type')->default('bin');                  // bin | staging | receiving | shipping | quarantine
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['inventory_warehouse_id', 'code']);
        });

        // Stock-on-hand — one row per (product, warehouse). The running balance
        // is maintained transactionally by InventoryService; average_cost is the
        // weighted-average unit cost used for inventory valuation.
        Schema::create('inventory_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_product_id')->constrained('inventory_products')->cascadeOnDelete();
            $table->foreignId('inventory_warehouse_id')->constrained('inventory_warehouses')->cascadeOnDelete();

            $table->decimal('quantity_on_hand', 18, 3)->default(0);
            $table->decimal('quantity_reserved', 18, 3)->default(0);
            $table->decimal('average_cost', 18, 4)->default(0);

            $table->timestamps();

            $table->unique(['inventory_product_id', 'inventory_warehouse_id'], 'inventory_stocks_product_warehouse_unique');
            $table->index('organization_id');
        });

        // Append-only stock ledger. `quantity` is signed (+ in / − out) so the
        // running balance is a simple sum; `quantity_after` snapshots the
        // (product, warehouse) on-hand right after this movement.
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->string('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('inventory_product_id')->constrained('inventory_products')->cascadeOnDelete();
            $table->foreignId('inventory_warehouse_id')->constrained('inventory_warehouses')->cascadeOnDelete();
            $table->foreignId('inventory_location_id')->nullable()->constrained('inventory_locations')->nullOnDelete();

            $table->string('type')->index();                         // App\Modules\Inventory\Enums\MovementType
            $table->decimal('quantity', 18, 3);                      // signed magnitude
            $table->decimal('unit_cost', 18, 4)->nullable();
            $table->decimal('quantity_after', 18, 3);

            $table->string('reference_type')->nullable();            // free-form link to a PO / work order / invoice
            $table->string('reference_id')->nullable();
            $table->string('transfer_group')->nullable()->index();   // ties the out+in pair of a transfer
            $table->text('note')->nullable();
            $table->timestamp('occurred_at')->useCurrent();

            $table->timestamps();

            $table->index(['inventory_product_id', 'inventory_warehouse_id'], 'inventory_movements_product_warehouse_index');
            $table->index(['organization_id', 'type']);
            $table->index('occurred_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('inventory_stocks');
        Schema::dropIfExists('inventory_locations');
        Schema::dropIfExists('inventory_warehouses');
        Schema::dropIfExists('inventory_products');
    }
};
