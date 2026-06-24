<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * External delivery/logistics vendors attached to a project — the forklift
 * company, trucking company, crane/rigging crew, etc. Plain contact records,
 * not linked to the CRM companies table (these are field service providers).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_project_vendors', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('crm_project_id')->constrained('crm_projects')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('category')->default('other');
            $table->string('company_name');
            $table->string('contact_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['crm_project_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_project_vendors');
    }
};
