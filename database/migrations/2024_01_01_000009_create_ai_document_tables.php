<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_parsing_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->morphs('document');
            $table->string('ai_provider')->default('fake');
            $table->string('status')->default('pending');
            $table->string('model_used')->nullable();
            $table->integer('input_tokens')->nullable();
            $table->integer('output_tokens')->nullable();
            $table->integer('duration_ms')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['status']);
        });

        Schema::create('document_extractions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_parsing_job_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->json('ai_output')->nullable()->comment('Raw AI extraction output');
            $table->json('human_corrected_output')->nullable()->comment('Human-reviewed and corrected data');
            $table->string('status')->default('pending');
            $table->boolean('is_human_reviewed')->default(false);
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('ai_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->morphs('subject');
            $table->string('analysis_type');
            $table->string('ai_provider')->default('fake');
            $table->string('model_used')->nullable();
            $table->text('prompt_used')->nullable();
            $table->json('context_data')->nullable();
            $table->json('output')->nullable();
            $table->string('status')->default('pending');
            $table->string('human_decision')->nullable()->comment('accepted|rejected|modified');
            $table->json('human_modified_output')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->integer('input_tokens')->nullable();
            $table->integer('output_tokens')->nullable();
            $table->timestamps();
            $table->index(['analysis_type', 'status']);
        });

        Schema::create('ai_prompts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->string('name');
            $table->string('analysis_type');
            $table->string('ai_provider')->nullable();
            $table->text('system_prompt');
            $table->text('user_prompt_template');
            $table->boolean('is_active')->default(true);
            $table->integer('version')->default(1);
            $table->timestamps();
        });

        Schema::create('ai_provider_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('provider');
            $table->string('model');
            $table->string('operation');
            $table->integer('input_tokens')->nullable();
            $table->integer('output_tokens')->nullable();
            $table->decimal('cost_usd', 10, 6)->nullable();
            $table->integer('duration_ms')->nullable();
            $table->boolean('success')->default(true);
            $table->string('error_code')->nullable();
            $table->timestamp('occurred_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_provider_logs');
        Schema::dropIfExists('ai_prompts');
        Schema::dropIfExists('ai_analyses');
        Schema::dropIfExists('document_extractions');
        Schema::dropIfExists('document_parsing_jobs');
    }
};
