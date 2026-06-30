<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 5 of the Project Field Information System: document management upgrade —
 * folders + version history on project files. Additive: a new folders table and
 * four new columns on crm_project_files. Existing files become version 1,
 * current, unfiled (column defaults), so nothing is lost.
 *
 * parent_file_id is the root of a version family (null = the file is its own
 * root); a family's "current" row carries is_current_version = true.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_project_folders', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('crm_project_id')->constrained('crm_projects')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'crm_project_id'], 'cpfold_org_proj_idx');
        });

        Schema::table('crm_project_files', function (Blueprint $table) {
            $table->foreignId('crm_project_folder_id')->nullable()->after('crm_project_id')
                ->constrained('crm_project_folders')->nullOnDelete();
            $table->unsignedInteger('version')->default(1)->after('source');
            $table->boolean('is_current_version')->default(true)->after('version');
            $table->unsignedBigInteger('parent_file_id')->nullable()->after('is_current_version');

            $table->index(['crm_project_id', 'is_current_version'], 'cpfile_proj_current_idx');
            $table->index('parent_file_id', 'cpfile_parent_idx');
        });
    }

    public function down(): void
    {
        Schema::table('crm_project_files', function (Blueprint $table) {
            $table->dropIndex('cpfile_proj_current_idx');
            $table->dropIndex('cpfile_parent_idx');
            $table->dropConstrainedForeignId('crm_project_folder_id');
            $table->dropColumn(['version', 'is_current_version', 'parent_file_id']);
        });

        Schema::dropIfExists('crm_project_folders');
    }
};
