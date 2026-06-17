<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Align the bidprime_imports / bidprime_import_items tables with their models and
 * the BidPrimeImportService, which were written against column names the original
 * migration never created (filters, total_*, error_message, item title). Without
 * these the importer threw "unknown column" and never logged a run.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bidprime_imports', function (Blueprint $table) {
            if (! Schema::hasColumn('bidprime_imports', 'filters')) {
                $table->json('filters')->nullable()->after('status');
            }
            foreach (['total_fetched', 'total_created', 'total_updated', 'total_skipped', 'total_errors'] as $col) {
                if (! Schema::hasColumn('bidprime_imports', $col)) {
                    $table->unsignedInteger($col)->default(0);
                }
            }
            if (! Schema::hasColumn('bidprime_imports', 'error_message')) {
                $table->text('error_message')->nullable();
            }
        });

        Schema::table('bidprime_import_items', function (Blueprint $table) {
            if (! Schema::hasColumn('bidprime_import_items', 'title')) {
                $table->string('title')->nullable()->after('external_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bidprime_imports', function (Blueprint $table) {
            $table->dropColumn(['filters', 'total_fetched', 'total_created', 'total_updated', 'total_skipped', 'total_errors', 'error_message']);
        });
        Schema::table('bidprime_import_items', function (Blueprint $table) {
            $table->dropColumn('title');
        });
    }
};
