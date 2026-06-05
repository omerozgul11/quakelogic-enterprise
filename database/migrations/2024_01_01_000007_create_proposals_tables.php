<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proposal_submissions', function (Blueprint $table) {
            $table->id();
            $table->string('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('opportunity_id')->nullable()->constrained('opportunities')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->foreignId('owner_id')->nullable()->constrained('users');
            $table->foreignId('proposal_manager_id')->nullable()->constrained('users');
            $table->foreignId('agency_id')->nullable()->constrained('agencies')->nullOnDelete();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();

            $table->string('proposal_number')->unique();
            $table->string('solicitation_number')->nullable();
            $table->string('project_name');
            $table->string('status')->default('draft')->index();

            $table->string('submission_channel')->nullable();
            $table->string('submission_confirmation_number')->nullable();
            $table->date('due_date')->nullable()->index();
            $table->date('submission_date')->nullable();
            $table->date('award_date')->nullable();

            $table->decimal('proposal_value', 18, 2)->nullable();
            $table->decimal('award_value', 18, 2)->nullable();
            $table->string('currency', 10)->default('USD');
            $table->decimal('estimated_margin', 5, 2)->nullable();

            $table->string('place_of_performance')->nullable();
            $table->string('period_of_performance')->nullable();
            $table->date('pop_start')->nullable();
            $table->date('pop_end')->nullable();

            $table->text('description')->nullable();
            $table->text('scope_summary')->nullable();
            $table->text('technical_approach_summary')->nullable();
            $table->text('loss_reason')->nullable();
            $table->text('lessons_learned')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'due_date']);
        });

        Schema::create('proposal_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proposal_submission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('changed_by')->constrained('users');
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->text('notes')->nullable();
            $table->timestamp('changed_at')->useCurrent();
        });

        Schema::create('proposal_team_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proposal_submission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('writer');
            $table->foreignId('assigned_by')->constrained('users');
            $table->timestamps();
            $table->unique(['proposal_submission_id', 'user_id']);
        });

        Schema::create('proposal_files', function (Blueprint $table) {
            $table->id();
            $table->string('ulid')->unique();
            $table->foreignId('proposal_submission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->string('display_name');
            $table->string('original_filename');
            $table->string('stored_filename');
            $table->string('disk')->default('local');
            $table->string('path');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size');
            $table->string('checksum', 64)->nullable();
            $table->string('document_type')->nullable();
            $table->string('status')->default('uploaded');
            $table->integer('version')->default(1);
            $table->boolean('is_current_version')->default(true);
            $table->unsignedBigInteger('parent_file_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['proposal_submission_id', 'is_current_version']);
        });

        Schema::create('proposal_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proposal_submission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->string('note_type')->default('general');
            $table->text('content');
            $table->boolean('is_private')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('compliance_matrices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proposal_submission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->string('title')->nullable();
            $table->string('status')->default('draft');
            $table->boolean('is_ai_generated')->default(false);
            $table->timestamps();
        });

        Schema::create('compliance_matrix_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compliance_matrix_id')->constrained()->cascadeOnDelete();
            $table->string('requirement_reference')->nullable();
            $table->text('requirement');
            $table->boolean('is_compliant')->nullable();
            $table->text('compliance_approach')->nullable();
            $table->string('owner')->nullable();
            $table->string('status')->default('pending');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compliance_matrix_items');
        Schema::dropIfExists('compliance_matrices');
        Schema::dropIfExists('proposal_notes');
        Schema::dropIfExists('proposal_files');
        Schema::dropIfExists('proposal_team_members');
        Schema::dropIfExists('proposal_status_history');
        Schema::dropIfExists('proposal_submissions');
    }
};
