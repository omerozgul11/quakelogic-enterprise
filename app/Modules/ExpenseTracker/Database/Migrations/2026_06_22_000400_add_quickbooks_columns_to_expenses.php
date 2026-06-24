<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds QuickBooks provenance columns to the (new, owned-by-this-feature)
 * expenses table. Purely additive — three nullable columns + an index on a
 * table this module created; no existing data is touched. `quickbooks_id` makes
 * imports idempotent (upsert per organization + remote id).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->string('source', 20)->default('manual')->after('status'); // manual|quickbooks
            $table->string('quickbooks_id')->nullable()->after('source');
            $table->timestamp('quickbooks_synced_at')->nullable()->after('quickbooks_id');

            $table->unique(['organization_id', 'quickbooks_id'], 'expenses_org_qbo_unique');
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropUnique('expenses_org_qbo_unique');
            $table->dropColumn(['source', 'quickbooks_id', 'quickbooks_synced_at']);
        });
    }
};
