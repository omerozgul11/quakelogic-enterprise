<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optional client (CRM company) on a purchase order — the customer the purchase
 * is ultimately for, distinct from the supplier/vendor. Suppliers sometimes need
 * to know the end client, so it's shown on the PO/PDF. Nullable; additive.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procurement_purchase_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('company_id')->nullable()->after('procurement_supplier_id');
            $table->foreign('company_id', 'po_company_id_fk')
                ->references('id')->on('companies')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('procurement_purchase_orders', function (Blueprint $table) {
            $table->dropForeign('po_company_id_fk');
            $table->dropColumn('company_id');
        });
    }
};
