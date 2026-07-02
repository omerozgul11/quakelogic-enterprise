<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Link a purchase order back to the purchase request (and/or accepted quotation)
 * it was raised from, so the PR → (Quotation) → PO chain is traceable. Nullable
 * — POs can still be created standalone, exactly as before.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procurement_purchase_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('procurement_purchase_request_id')->nullable()->after('procurement_supplier_id');
            $table->foreign('procurement_purchase_request_id', 'po_pur_request_id_fk')
                ->references('id')->on('procurement_purchase_requests')->nullOnDelete();

            $table->unsignedBigInteger('procurement_quotation_id')->nullable()->after('procurement_purchase_request_id');
            $table->foreign('procurement_quotation_id', 'po_quotation_id_fk')
                ->references('id')->on('procurement_quotations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('procurement_purchase_orders', function (Blueprint $table) {
            $table->dropForeign('po_pur_request_id_fk');
            $table->dropForeign('po_quotation_id_fk');
            $table->dropColumn(['procurement_purchase_request_id', 'procurement_quotation_id']);
        });
    }
};
