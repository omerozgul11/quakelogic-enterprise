<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bills — the vendor invoice raised against a purchase order (the "bill" step
 * after a PO). Tracks payment status (unpaid → partially paid → paid) from the
 * approved payments recorded against it, supports per-payment approval, and can
 * recur on a schedule. Additive, `procurement_`-prefixed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procurement_bills', function (Blueprint $table) {
            $table->id();
            $table->string('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('procurement_supplier_id')->constrained('procurement_suppliers')->cascadeOnDelete();

            $table->unsignedBigInteger('procurement_purchase_order_id')->nullable();
            $table->foreign('procurement_purchase_order_id', 'bills_po_id_fk')
                ->references('id')->on('procurement_purchase_orders')->nullOnDelete();

            $table->string('number');                              // our internal bill number, BILL-YYYY-NNNN
            $table->string('vendor_invoice_number')->nullable();   // the vendor's own invoice number
            $table->date('bill_date')->nullable();
            $table->date('due_date')->nullable();
            $table->string('currency', 3)->default('USD');

            $table->decimal('subtotal', 18, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('tax_amount', 18, 2)->default(0);
            $table->decimal('shipping_amount', 18, 2)->default(0);
            $table->decimal('discount_total', 18, 2)->default(0);
            $table->decimal('total', 18, 2)->default(0);
            $table->decimal('amount_paid', 18, 2)->default(0);     // sum of approved payments

            $table->string('payment_status')->default('unpaid')->index();  // BillPaymentStatus

            // Recurring: generate the next bill on next_recurring_date until the
            // total cycle count is reached (0 = no limit). recurring_parent_id
            // points at the originating (template) bill for generated copies.
            $table->boolean('recurring')->default(false);
            $table->string('recurring_frequency')->nullable();     // weekly|monthly|quarterly|yearly
            $table->unsignedInteger('recurring_cycles')->default(0);
            $table->unsignedInteger('recurring_total_cycles')->default(0);
            $table->date('next_recurring_date')->nullable();
            $table->unsignedBigInteger('recurring_parent_id')->nullable();
            $table->foreign('recurring_parent_id', 'bills_recurring_parent_fk')
                ->references('id')->on('procurement_bills')->nullOnDelete();

            $table->text('notes')->nullable();
            $table->text('terms')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'number']);
            $table->index(['organization_id', 'payment_status']);
        });

        Schema::create('procurement_bill_items', function (Blueprint $table) {
            $table->id();
            $table->string('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();

            $table->unsignedBigInteger('procurement_bill_id');
            $table->foreign('procurement_bill_id', 'bill_items_bill_id_fk')
                ->references('id')->on('procurement_bills')->cascadeOnDelete();

            $table->unsignedBigInteger('inventory_product_id')->nullable();
            $table->foreign('inventory_product_id', 'bill_items_product_id_fk')
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

            $table->index('procurement_bill_id', 'bill_items_bill_id_idx');
        });

        Schema::create('procurement_bill_payments', function (Blueprint $table) {
            $table->id();
            $table->string('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();

            $table->unsignedBigInteger('procurement_bill_id');
            $table->foreign('procurement_bill_id', 'bill_pay_bill_id_fk')
                ->references('id')->on('procurement_bills')->cascadeOnDelete();

            $table->decimal('amount', 18, 2);
            $table->string('payment_method')->nullable();
            $table->date('paid_on');
            $table->string('reference')->nullable();               // transaction / cheque reference
            $table->text('note')->nullable();

            // Per-payment approval: only 'approved' payments count toward amount_paid.
            $table->string('approval_status')->default('approved'); // pending|approved|rejected
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();

            $table->timestamps();

            $table->index('procurement_bill_id', 'bill_pay_bill_id_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_bill_payments');
        Schema::dropIfExists('procurement_bill_items');
        Schema::dropIfExists('procurement_bills');
    }
};
