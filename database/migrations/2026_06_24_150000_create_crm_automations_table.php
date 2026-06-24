<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rules-based CRM automations: when a trigger fires and the (optional) conditions
 * match, run a list of safe in-app actions (create a follow-up, notify, assign,
 * log a note). Conditions and actions are stored as JSON so new kinds can be
 * added without a schema change. Additive; nothing existing changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_automations', function (Blueprint $table) {
            $table->id();
            $table->string('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('name');
            $table->boolean('is_active')->default(true);
            // lead.created | lead.stage_changed
            $table->string('trigger_event', 40);
            $table->json('conditions')->nullable();
            $table->json('actions');

            $table->unsignedInteger('run_count')->default(0);
            $table->timestamp('last_run_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'trigger_event', 'is_active'], 'crm_automations_trigger_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_automations');
    }
};
