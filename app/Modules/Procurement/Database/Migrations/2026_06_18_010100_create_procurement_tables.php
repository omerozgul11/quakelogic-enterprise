<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Procurement module — supplier master, purchase orders + line items.
 * Additive only, `procurement_`-prefixed. Purchase-order receiving feeds the
 * Inventory module (inventory_products / inventory_warehouses) via
 * InventoryService, but Procurement owns suppliers and the PO lifecycle.
 * Long auto-generated identifiers are named explicitly to stay <= 64 chars.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procurement_suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();

            $table->string('code');
            $table->string('name');
            $table->string('category')->nullable()->index();
            $table->string('status')->default('active')->index();   // App\Modules\Procurement\Enums\SupplierStatus
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('website')->nullable();
            $table->string('address_line1')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('country')->nullable();
            $table->string('payment_terms')->nullable();             // e.g. "Net 30"
            $table->string('currency', 3)->default('USD');
            $table->string('tax_id')->nullable();
            $table->unsignedInteger('lead_time_days')->nullable();
            $table->unsignedTinyInteger('rating')->nullable();       // 1–5
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'code']);
            $table->index(['organization_id', 'status']);
        });

        Schema::create('procurement_supplier_contacts', function (Blueprint $table) {
            $table->id();
            $table->string('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('procurement_supplier_id')->constrained('procurement_suppliers')->cascadeOnDelete();

            $table->string('name');
            $table->string('title')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->boolean('is_primary')->default(false);

            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('procurement_purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('procurement_supplier_id')->constrained('procurement_suppliers')->cascadeOnDelete();
            $table->foreignId('inventory_warehouse_id')->nullable()->constrained('inventory_warehouses')->nullOnDelete();

            $table->string('number');
            $table->string('status')->default('draft')->index();     // App\Modules\Procurement\Enums\PurchaseOrderStatus
            $table->date('order_date')->nullable();
            $table->date('expected_date')->nullable();
            $table->string('currency', 3)->default('USD');

            $table->decimal('subtotal', 18, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('tax_amount', 18, 2)->default(0);
            $table->decimal('shipping_amount', 18, 2)->default(0);
            $table->decimal('total', 18, 2)->default(0);

            $table->text('notes')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'number']);
            $table->index(['organization_id', 'status']);
        });

        Schema::create('procurement_purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->string('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();

            // Explicit names: the auto-generated ones exceed MariaDB's 64-char limit.
            $table->unsignedBigInteger('procurement_purchase_order_id');
            $table->foreign('procurement_purchase_order_id', 'po_items_po_id_fk')
                ->references('id')->on('procurement_purchase_orders')->cascadeOnDelete();

            $table->foreignId('inventory_product_id')->nullable()->constrained('inventory_products')->nullOnDelete();

            $table->string('description');
            $table->string('sku')->nullable();
            $table->decimal('quantity_ordered', 18, 3)->default(0);
            $table->decimal('quantity_received', 18, 3)->default(0);
            $table->decimal('unit_cost', 18, 4)->default(0);
            $table->decimal('line_total', 18, 2)->default(0);
            $table->integer('position')->default(0);

            $table->timestamps();

            $table->index('procurement_purchase_order_id', 'po_items_po_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_purchase_order_items');
        Schema::dropIfExists('procurement_purchase_orders');
        Schema::dropIfExists('procurement_supplier_contacts');
        Schema::dropIfExists('procurement_suppliers');
    }
};
