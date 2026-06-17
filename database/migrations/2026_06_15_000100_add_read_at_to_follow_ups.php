<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('follow_ups', function (Blueprint $table) {
            // When the recipient has read this inbox message. Null = unread.
            $table->timestamp('read_at')->nullable()->after('responded_at');
        });

        // Treat everything that predates this feature as already read, so the new
        // unread badge starts at zero instead of flagging months of old messages.
        // Only brand-new activity from here on shows as unread.
        DB::table('follow_ups')->whereNull('read_at')->update(['read_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('follow_ups', function (Blueprint $table) {
            $table->dropColumn('read_at');
        });
    }
};
