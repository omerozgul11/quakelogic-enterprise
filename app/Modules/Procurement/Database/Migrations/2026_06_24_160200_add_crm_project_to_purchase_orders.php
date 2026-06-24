<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets a purchase order be associated with a delivery project in the Project
 * Management app. Nullable — POs raised outside any project stay unlinked. Kept
 * in the Procurement module (the table it alters lives here) so it only runs
 * while the module is enabled.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procurement_purchase_orders', function (Blueprint $table) {
            $table->foreignId('crm_project_id')->nullable()->after('organization_id')
                ->constrained('crm_projects')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('procurement_purchase_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('crm_project_id');
        });
    }
};
