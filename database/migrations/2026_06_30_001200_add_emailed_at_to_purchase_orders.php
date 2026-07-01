<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records when a purchase order was emailed to the supplier (set when it's
 * marked Sent and a vendor email is on file). Purely additive.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procurement_purchase_orders', function (Blueprint $table) {
            $table->timestamp('emailed_at')->nullable()->after('approved_at');
        });
    }

    public function down(): void
    {
        Schema::table('procurement_purchase_orders', function (Blueprint $table) {
            $table->dropColumn('emailed_at');
        });
    }
};
