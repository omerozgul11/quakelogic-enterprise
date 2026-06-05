<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('capture_plans', function (Blueprint $table) {
            $table->id();
            $table->string('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('opportunity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('capture_manager_id')->nullable()->constrained('users');
            $table->foreignId('created_by')->constrained('users');
            $table->string('stage')->default('discovery');
            $table->decimal('probability_of_win', 5, 2)->nullable();
            $table->decimal('estimated_value', 18, 2)->nullable();
            $table->decimal('estimated_margin', 5, 2)->nullable();
            $table->text('strategy')->nullable();
            $table->text('win_themes')->nullable();
            $table->text('discriminators')->nullable();
            $table->text('risks_summary')->nullable();
            $table->boolean('is_incumbent')->default(false);
            $table->string('incumbent_name')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('capture_stage_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('capture_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('changed_by')->constrained('users');
            $table->string('from_stage')->nullable();
            $table->string('to_stage');
            $table->text('notes')->nullable();
            $table->timestamp('changed_at')->useCurrent();
        });

        Schema::create('capture_risks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('capture_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('likelihood')->default('medium');
            $table->string('impact')->default('medium');
            $table->string('risk_score')->nullable();
            $table->text('mitigation_strategy')->nullable();
            $table->string('status')->default('open');
            $table->timestamps();
        });

        Schema::create('capture_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('capture_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users');
            $table->foreignId('created_by')->constrained('users');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status')->default('open');
            $table->date('due_date')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('capture_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('capture_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('decided_by')->constrained('users');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('decision');
            $table->text('rationale')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('capture_decisions');
        Schema::dropIfExists('capture_tasks');
        Schema::dropIfExists('capture_risks');
        Schema::dropIfExists('capture_stage_history');
        Schema::dropIfExists('capture_plans');
    }
};
