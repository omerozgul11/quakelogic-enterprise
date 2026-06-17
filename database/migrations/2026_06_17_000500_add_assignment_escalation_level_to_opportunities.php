<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Highest assignment-inaction escalation tier (0/24/48/72/96 hours) already
 * alerted on for an opportunity, so the hourly escalation job never re-spams a
 * tier. Reset to 0 whenever the opportunity is (re)assigned or claimed, which
 * restarts the clock for the new owner.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opportunities', function (Blueprint $table) {
            $table->unsignedSmallInteger('assignment_escalation_level')->default(0)->after('ownership_locked_at');
        });
    }

    public function down(): void
    {
        Schema::table('opportunities', function (Blueprint $table) {
            $table->dropColumn('assignment_escalation_level');
        });
    }
};
