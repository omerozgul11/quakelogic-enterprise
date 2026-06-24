<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('proposal_mailings', function (Blueprint $table) {
            // Carrier reference-type for the tracking number (J.B. Hunt only):
            // orderNbr | utn | BOL | poNbr | shipperId | pickupNbr | deliveryApptNbr | sealNbr.
            // Drives the J.B. Hunt deep link (?k=<type>&v=<number>); null for carriers
            // that don't use reference types (e.g. UPS).
            $table->string('reference_type', 20)->nullable()->after('ups_tracking_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('proposal_mailings', function (Blueprint $table) {
            $table->dropColumn('reference_type');
        });
    }
};
