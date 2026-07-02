<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The supplier ↔ inventory-product link: which products a supplier sells us, at
 * what part number and price (their price = our purchasing cost). Populated by
 * dropping a supplier's price list / product sheet on their detail page. A
 * product can be linked to several suppliers (price comparison).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procurement_supplier_products', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_product_id')->constrained('inventory_products')->cascadeOnDelete();
            $table->foreignId('procurement_supplier_id')->constrained('procurement_suppliers')->cascadeOnDelete();
            $table->string('supplier_sku')->nullable();
            $table->decimal('supplier_price', 18, 4)->nullable();
            $table->string('currency', 3)->default('USD');
            $table->unsignedInteger('lead_time_days')->nullable();
            $table->timestamp('last_imported_at')->nullable();
            $table->string('source_document')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['inventory_product_id', 'procurement_supplier_id'], 'psp_product_supplier_unique');
            $table->index(['organization_id', 'procurement_supplier_id'], 'psp_org_supplier_idx');
            $table->index(['procurement_supplier_id', 'supplier_sku'], 'psp_supplier_sku_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_supplier_products');
    }
};
