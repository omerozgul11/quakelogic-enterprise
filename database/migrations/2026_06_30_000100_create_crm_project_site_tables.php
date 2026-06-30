<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Field-installation site briefing for a project — the "everything you need
 * before you leave for site" data set. A project has one or more installation
 * sites, each carrying its access/security/utility/safety profile, plus a list
 * of typed stakeholder contacts (procurement, facilities, security, …).
 *
 * Purely additive: new tables hanging off crm_projects. Nothing existing is
 * altered. Tri-state booleans are nullable (null = "unknown / not captured").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_project_sites', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('crm_project_id')->constrained('crm_projects')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('name');
            $table->boolean('is_primary')->default(false);

            // Location
            $table->text('address')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('maps_url', 1000)->nullable();

            // Access & on-site logistics
            $table->text('access_instructions')->nullable();
            $table->string('loading_dock')->nullable();
            $table->string('parking')->nullable();
            $table->string('working_hours')->nullable();
            $table->string('gate_hours')->nullable();

            // Security & PPE (tri-state: null = unknown)
            $table->text('security_requirements')->nullable();
            $table->boolean('badge_required')->nullable();
            $table->boolean('escort_required')->nullable();
            $table->string('ppe_required')->nullable();

            // Site resources / utilities (tri-state + free notes)
            $table->boolean('forklift_available')->nullable();
            $table->boolean('crane_available')->nullable();
            $table->boolean('internet_available')->nullable();
            $table->boolean('power_available')->nullable();
            $table->boolean('water_available')->nullable();
            $table->boolean('compressed_air_available')->nullable();
            $table->text('utilities_notes')->nullable();
            $table->text('environmental_conditions')->nullable();

            // Safety (site-specific)
            $table->text('hazards')->nullable();
            $table->text('lockout_tagout')->nullable();
            $table->boolean('high_voltage')->nullable();
            $table->boolean('confined_space')->nullable();
            $table->boolean('fall_protection')->nullable();
            $table->text('chemical_hazards')->nullable();
            $table->string('emergency_assembly_point')->nullable();
            $table->string('nearest_hospital')->nullable();
            $table->string('hospital_phone')->nullable();
            $table->string('police_phone')->nullable();
            $table->string('fire_phone')->nullable();
            $table->string('site_safety_contact')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'crm_project_id']);
            $table->index(['crm_project_id', 'is_primary']);
        });

        Schema::create('crm_project_contacts', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('crm_project_id')->constrained('crm_projects')->cascadeOnDelete();
            $table->foreignId('crm_project_site_id')->nullable()->constrained('crm_project_sites')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('category')->default('other');
            $table->string('name');
            $table->string('title')->nullable();
            $table->string('company')->nullable();
            $table->string('phone')->nullable();
            $table->string('mobile')->nullable();
            $table->string('email')->nullable();
            $table->string('preferred_contact_method')->nullable();
            $table->string('availability')->nullable();
            $table->boolean('is_emergency')->default(false);
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['crm_project_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_project_contacts');
        Schema::dropIfExists('crm_project_sites');
    }
};
