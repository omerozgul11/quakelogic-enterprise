<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Use QuakeLogic's shipping account" flag on a purchase order — when set, the
 * vendor email draft asks the supplier to ship on our carrier account rather
 * than prepaying. Additive; off by default.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procurement_purchase_orders', function (Blueprint $table) {
            $table->boolean('use_ql_shipping_account')->default(false)->after('shipping_terms');
        });
    }

    public function down(): void
    {
        Schema::table('procurement_purchase_orders', function (Blueprint $table) {
            $table->dropColumn('use_ql_shipping_account');
        });
    }
};
