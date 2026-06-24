<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Field-delivery / logistics details for the Project Management app: the site
 * address, an on-site point of contact, contract/order reference numbers, free
 * logistics notes and a specifications block. Vendor contacts (forklift,
 * trucking, …) and purchase-order links live in their own tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_projects', function (Blueprint $table) {
            $table->text('address')->nullable()->after('notes');
            $table->string('poc_name')->nullable()->after('address');
            $table->string('poc_role')->nullable()->after('poc_name');
            $table->string('poc_phone')->nullable()->after('poc_role');
            $table->string('poc_email')->nullable()->after('poc_phone');
            $table->text('reference_numbers')->nullable()->after('poc_email');
            $table->text('logistics')->nullable()->after('reference_numbers');
            $table->text('specs')->nullable()->after('logistics');
        });
    }

    public function down(): void
    {
        Schema::table('crm_projects', function (Blueprint $table) {
            $table->dropColumn([
                'address', 'poc_name', 'poc_role', 'poc_phone', 'poc_email',
                'reference_numbers', 'logistics', 'specs',
            ]);
        });
    }
};
