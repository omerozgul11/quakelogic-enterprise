<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Project Management upgrade for the /crm section. Turns the lean crm_projects /
 * crm_tasks core into a full delivery workspace: links a project back to the
 * proposal/opportunity it was won from, adds a dedicated project number and
 * project manager, and introduces team members, milestones, notes, files, a
 * per-project activity feed, task comments and org-level settings.
 *
 * Additive and reversible. Existing crm_projects/crm_tasks rows are remapped to
 * the new status / priority vocabulary in-place so the enum casts keep working.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_projects', function (Blueprint $table) {
            // The proposal/opportunity this project was won from (nullable — a
            // project can still be created manually with no source).
            $table->foreignId('proposal_submission_id')->nullable()->after('company_id')
                ->constrained('proposal_submissions')->nullOnDelete();
            $table->foreignId('opportunity_id')->nullable()->after('proposal_submission_id')
                ->constrained('opportunities')->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->after('opportunity_id')
                ->constrained('contacts')->nullOnDelete();

            // A distinct, sequential project number (QL-PROJ-YYYY-NNNN) separate
            // from the free-form `code`.
            $table->string('project_number')->nullable()->after('code');

            // The responsible delivery lead — may differ from the owner. The owner
            // defaults to the proposal owner; an admin can reassign either.
            $table->foreignId('project_manager_id')->nullable()->after('owner_id')
                ->constrained('users')->nullOnDelete();

            $table->text('notes')->nullable()->after('description');
            // 'manual' | 'automatic' — how the project came to exist.
            $table->string('created_via')->default('manual')->after('progress');

            $table->unique(['organization_id', 'project_number'], 'crm_projects_org_number_unique');
            $table->index('proposal_submission_id', 'crm_projects_proposal_idx');
        });

        // Remap the old status vocabulary to the new lifecycle so the enum cast
        // keeps resolving. planned→new, active→in_progress; on_hold/completed/
        // cancelled are unchanged. Done as a plain UPDATE (no model events).
        DB::table('crm_projects')->where('status', 'planned')->update(['status' => 'new']);
        DB::table('crm_projects')->where('status', 'active')->update(['status' => 'in_progress']);

        // The default project status is now "new".
        DB::statement("ALTER TABLE crm_projects ALTER COLUMN status SET DEFAULT 'new'");

        // Task priorities move to low|medium|high|critical. The old 'urgent'
        // becomes 'critical'.
        DB::table('crm_tasks')->where('priority', 'urgent')->update(['priority' => 'critical']);

        // Team members on a project. Role + responsibility are free-form labels;
        // is_active lets a member be deactivated without losing the history.
        Schema::create('crm_project_members', function (Blueprint $table) {
            $table->id();
            $table->string('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('crm_project_id')->constrained('crm_projects')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('role')->default('member');           // manager | lead | member | viewer | …
            $table->string('responsibility')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique(['crm_project_id', 'user_id'], 'crm_project_members_unique');
        });

        // Milestones / timeline entries for a project.
        Schema::create('crm_project_milestones', function (Blueprint $table) {
            $table->id();
            $table->string('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('crm_project_id')->constrained('crm_projects')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('title');
            $table->text('description')->nullable();
            $table->date('due_date')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('status')->default('pending');         // pending | in_progress | completed
            $table->integer('sort_order')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['crm_project_id', 'status'], 'crm_milestones_project_status_idx');
        });

        // Free-form notes / updates on a project.
        Schema::create('crm_project_notes', function (Blueprint $table) {
            $table->id();
            $table->string('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('crm_project_id')->constrained('crm_projects')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->text('body');

            $table->timestamps();
            $table->softDeletes();

            $table->index('crm_project_id', 'crm_project_notes_project_idx');
        });

        // Files attached to a project (private `local` disk, streamed via the
        // controller only — mirrors the proposal/mailing file convention).
        Schema::create('crm_project_files', function (Blueprint $table) {
            $table->id();
            $table->string('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('crm_project_id')->constrained('crm_projects')->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('display_name');
            $table->string('original_filename');
            $table->string('stored_filename');
            $table->string('disk')->default('local');
            $table->string('path');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->string('checksum')->nullable();
            // 'upload' | 'proposal' — where the file came from (award copy vs. manual).
            $table->string('source')->default('upload');

            $table->timestamps();
            $table->softDeletes();

            $table->index('crm_project_id', 'crm_project_files_project_idx');
        });

        // Human-readable per-project activity feed (audit trail). Distinct from
        // the global audit_logs trail — this is the project-scoped story shown on
        // the detail page. user_id null = system/automation.
        Schema::create('crm_project_activities', function (Blueprint $table) {
            $table->id();
            $table->string('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('crm_project_id')->constrained('crm_projects')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('action');                 // created | status_changed | member_added | …
            $table->string('description');            // rendered sentence
            $table->json('meta')->nullable();

            $table->timestamps();

            $table->index(['crm_project_id', 'created_at'], 'crm_project_activities_feed_idx');
        });

        // Comments on a task.
        Schema::create('crm_task_comments', function (Blueprint $table) {
            $table->id();
            $table->string('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('crm_task_id')->constrained('crm_tasks')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->text('body');

            $table->timestamps();
            $table->softDeletes();

            $table->index('crm_task_id', 'crm_task_comments_task_idx');
        });

        // One settings row per organization (singleton). Governs the award→project
        // automation, numbering and notification behaviour.
        Schema::create('crm_project_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->unique()->constrained()->cascadeOnDelete();

            $table->boolean('auto_create_on_award')->default(true);
            $table->string('default_status')->default('new');
            // proposal_owner | proposal_creator | unassigned
            $table->string('default_manager_rule')->default('proposal_owner');
            $table->string('number_prefix')->default('QL-PROJ');
            $table->boolean('notify_on_create')->default(true);
            $table->json('default_member_ids')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_project_settings');
        Schema::dropIfExists('crm_task_comments');
        Schema::dropIfExists('crm_project_activities');
        Schema::dropIfExists('crm_project_files');
        Schema::dropIfExists('crm_project_notes');
        Schema::dropIfExists('crm_project_milestones');
        Schema::dropIfExists('crm_project_members');

        DB::statement("ALTER TABLE crm_projects ALTER COLUMN status SET DEFAULT 'planned'");
        DB::table('crm_projects')->where('status', 'new')->update(['status' => 'planned']);
        DB::table('crm_projects')->where('status', 'in_progress')->update(['status' => 'active']);
        DB::table('crm_tasks')->where('priority', 'critical')->update(['priority' => 'urgent']);

        Schema::table('crm_projects', function (Blueprint $table) {
            $table->dropForeign(['proposal_submission_id']);
            $table->dropForeign(['opportunity_id']);
            $table->dropForeign(['contact_id']);
            $table->dropForeign(['project_manager_id']);
            $table->dropUnique('crm_projects_org_number_unique');
            $table->dropIndex('crm_projects_proposal_idx');
            $table->dropColumn([
                'proposal_submission_id', 'opportunity_id', 'contact_id',
                'project_number', 'project_manager_id', 'notes', 'created_via',
            ]);
        });
    }
};
