<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Manufacturing module — bills of materials + work orders. Additive,
 * `manufacturing_`-prefixed. Material movement (component consumption and
 * finished-goods production) is recorded in the Inventory ledger
 * (inventory_movements, reference_type='manufacturing_work_order'), so there is
 * no separate components table — the ledger is the source of truth.
 */
return new class extends Migration
{
    public function up(): void
    {
        // A bill of materials: the recipe to build `output_quantity` units of an
        // output product from its component lines.
        Schema::create('manufacturing_boms', function (Blueprint $table) {
            $table->id();
            $table->string('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('inventory_product_id')->constrained('inventory_products')->cascadeOnDelete();

            $table->string('name');
            $table->string('version')->default('v1');
            $table->string('status')->default('active')->index();    // App\Modules\Manufacturing\Enums\BomStatus
            $table->decimal('output_quantity', 18, 3)->default(1);
            $table->boolean('is_default')->default(false);
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'status']);
        });

        Schema::create('manufacturing_bom_items', function (Blueprint $table) {
            $table->id();
            $table->string('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('manufacturing_bom_id')->constrained('manufacturing_boms')->cascadeOnDelete();
            $table->foreignId('inventory_product_id')->constrained('inventory_products')->cascadeOnDelete();

            $table->decimal('quantity_per', 18, 3);                  // per output_quantity units
            $table->string('notes')->nullable();
            $table->integer('position')->default(0);

            $table->timestamps();
        });

        // An order to build a quantity of a product. Completing it consumes the
        // BOM components from, and produces finished goods into, the warehouse.
        Schema::create('manufacturing_work_orders', function (Blueprint $table) {
            $table->id();
            $table->string('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('inventory_product_id')->constrained('inventory_products')->cascadeOnDelete();
            $table->foreignId('manufacturing_bom_id')->nullable()->constrained('manufacturing_boms')->nullOnDelete();
            $table->foreignId('inventory_warehouse_id')->constrained('inventory_warehouses')->cascadeOnDelete();

            $table->string('number');
            $table->string('status')->default('draft')->index();     // App\Modules\Manufacturing\Enums\WorkOrderStatus
            $table->decimal('quantity_planned', 18, 3)->default(0);
            $table->decimal('quantity_produced', 18, 3)->default(0);
            $table->decimal('build_cost', 18, 4)->default(0);        // total component cost consumed
            $table->date('scheduled_date')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'number']);
            $table->index(['organization_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manufacturing_work_orders');
        Schema::dropIfExists('manufacturing_bom_items');
        Schema::dropIfExists('manufacturing_boms');
    }
};
