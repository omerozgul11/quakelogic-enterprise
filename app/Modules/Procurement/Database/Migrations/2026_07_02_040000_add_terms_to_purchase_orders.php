<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-PO payment & shipping terms. Previously a PO only showed the supplier's
 * default `payment_terms`; these let each order carry its own terms — e.g.
 * "Net 30" and a shipping method / incoterm like "DHL". Both nullable and
 * additive: existing POs are unaffected and continue to fall back to the
 * supplier default in the UI when blank.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procurement_purchase_orders', function (Blueprint $table) {
            $table->string('payment_terms')->nullable()->after('notes');
            $table->string('shipping_terms')->nullable()->after('payment_terms');
        });
    }

    public function down(): void
    {
        Schema::table('procurement_purchase_orders', function (Blueprint $table) {
            $table->dropColumn(['payment_terms', 'shipping_terms']);
        });
    }
};
