<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 4 of the Project Field Information System: travel arrangements for a
 * project trip — flights, lodging, car rental, ground transport, per-diem and
 * incidentals, each with traveller, schedule, route, confirmation and cost.
 *
 * Purely additive. Emergency numbers / maps already live on the site briefing
 * (Phase 1), so they're not duplicated here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_project_travel', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('crm_project_id')->constrained('crm_projects')->cascadeOnDelete();
            $table->foreignId('traveler_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('traveler_name')->nullable();
            $table->string('type');                    // TravelType
            $table->string('title');
            $table->string('status')->nullable();      // planned | booked | completed | cancelled
            $table->string('provider')->nullable();    // airline / hotel / rental company
            $table->string('confirmation_number')->nullable();
            $table->dateTime('start_at')->nullable();
            $table->dateTime('end_at')->nullable();
            $table->string('from_location')->nullable();
            $table->string('to_location')->nullable();
            $table->decimal('cost', 12, 2)->nullable();
            $table->string('currency', 3)->nullable();
            $table->string('booking_url', 1000)->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'crm_project_id'], 'cptr_org_proj_idx');
            $table->index(['crm_project_id', 'type'], 'cptr_proj_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_project_travel');
    }
};
