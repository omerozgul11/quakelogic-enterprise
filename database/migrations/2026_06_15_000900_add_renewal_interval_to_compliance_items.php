<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('compliance_items', function (Blueprint $table) {
            // Renewal cadence: none / monthly / quarterly / semiannual / annual / biennial.
            $table->string('renewal_interval')->nullable()->after('expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('compliance_items', function (Blueprint $table) {
            $table->dropColumn('renewal_interval');
        });
    }
};
