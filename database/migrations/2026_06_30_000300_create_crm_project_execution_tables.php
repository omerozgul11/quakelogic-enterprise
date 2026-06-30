<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3 of the Project Field Information System: on-site execution.
 *
 * - crm_project_execution_records: one unified, typed table for installation /
 *   commissioning / training / warranty / inspection / service events (avoids
 *   four near-identical tables).
 * - crm_project_checklists + items: reusable multi-step lists (pre-departure,
 *   required tools/spares, punch list, customer requests …) with tick-off.
 *
 * Purely additive — hangs off crm_projects, optionally tied to a site.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_project_execution_records', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('crm_project_id')->constrained('crm_projects')->cascadeOnDelete();
            $table->foreignId('crm_project_site_id')->nullable()->constrained('crm_project_sites')->nullOnDelete();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('type');                  // ExecutionRecordType
            $table->string('title');
            $table->string('status')->default('scheduled'); // ExecutionRecordStatus
            $table->date('scheduled_date')->nullable();
            $table->date('completed_date')->nullable();
            $table->text('summary')->nullable();     // scope / plan / special instructions
            $table->text('outcome')->nullable();     // results / lessons learned / completion notes
            $table->boolean('customer_visible')->default(false);
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'crm_project_id'], 'cper_org_proj_idx');
            $table->index(['crm_project_id', 'type'], 'cper_proj_type_idx');
        });

        Schema::create('crm_project_checklists', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('crm_project_id')->constrained('crm_projects')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('title');
            $table->string('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'crm_project_id'], 'cpcl_org_proj_idx');
        });

        Schema::create('crm_project_checklist_items', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('crm_project_checklist_id')->constrained('crm_project_checklists')->cascadeOnDelete();
            $table->foreignId('done_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('text');
            $table->boolean('is_done')->default(false);
            $table->unsignedInteger('position')->default(0);
            $table->timestamp('done_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['crm_project_checklist_id', 'position'], 'cpci_chk_pos_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_project_checklist_items');
        Schema::dropIfExists('crm_project_checklists');
        Schema::dropIfExists('crm_project_execution_records');
    }
};
