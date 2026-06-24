<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Leads get an explicit free-text company name and a product name (the QuakeLogic
 * product the lead is interested in — a Products section comes later). Both are
 * required at the application layer; nullable here so existing rows aren't broken,
 * with company_name back-filled from any linked client.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_leads', function (Blueprint $table) {
            $table->string('company_name')->nullable()->after('company_id');
            $table->string('product_name')->nullable()->after('contact_name');
        });

        // Seed company_name from the linked client where we have one.
        DB::statement(
            'UPDATE crm_leads l JOIN companies c ON c.id = l.company_id '
            .'SET l.company_name = c.name '
            .'WHERE l.company_name IS NULL AND l.company_id IS NOT NULL'
        );
    }

    public function down(): void
    {
        Schema::table('crm_leads', function (Blueprint $table) {
            $table->dropColumn(['company_name', 'product_name']);
        });
    }
};
