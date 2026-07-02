<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quotations (RFQ) — the optional middle step: request quotes from one or more
 * vendors against a purchase request, record their prices, then accept one and
 * raise a purchase order from it. Additive, `procurement_`-prefixed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procurement_quotations', function (Blueprint $table) {
            $table->id();
            $table->string('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users');

            $table->unsignedBigInteger('procurement_purchase_request_id')->nullable();
            $table->foreign('procurement_purchase_request_id', 'quo_pr_id_fk')
                ->references('id')->on('procurement_purchase_requests')->nullOnDelete();

            $table->foreignId('procurement_supplier_id')->constrained('procurement_suppliers')->cascadeOnDelete();

            $table->string('number');
            $table->string('reference_no')->nullable();
            $table->string('status')->default('draft')->index();   // App\Modules\Procurement\Enums\QuotationStatus
            $table->date('quote_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('currency', 3)->default('USD');

            $table->decimal('subtotal', 18, 2)->default(0);
            $table->decimal('tax_amount', 18, 2)->default(0);
            $table->decimal('discount_total', 18, 2)->default(0);
            $table->decimal('total', 18, 2)->default(0);

            $table->text('vendor_note')->nullable();
            $table->text('admin_note')->nullable();
            $table->text('terms')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('accepted_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'number']);
            $table->index(['organization_id', 'status']);
        });

        Schema::create('procurement_quotation_items', function (Blueprint $table) {
            $table->id();
            $table->string('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();

            $table->unsignedBigInteger('procurement_quotation_id');
            $table->foreign('procurement_quotation_id', 'quo_items_quo_id_fk')
                ->references('id')->on('procurement_quotations')->cascadeOnDelete();

            $table->unsignedBigInteger('inventory_product_id')->nullable();
            $table->foreign('inventory_product_id', 'quo_items_product_id_fk')
                ->references('id')->on('inventory_products')->nullOnDelete();

            $table->string('description');
            $table->string('sku')->nullable();
            $table->string('unit')->nullable();
            $table->decimal('quantity', 18, 3)->default(0);
            $table->decimal('unit_cost', 18, 4)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('line_total', 18, 2)->default(0);
            $table->integer('position')->default(0);

            $table->timestamps();

            $table->index('procurement_quotation_id', 'quo_items_quo_id_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_quotation_items');
        Schema::dropIfExists('procurement_quotations');
    }
};
