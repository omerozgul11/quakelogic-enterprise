<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('assigned_to')->nullable()->constrained('users');
            $table->morphs('taskable');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status')->default('open');
            $table->string('priority')->default('medium');
            $table->date('due_date')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['assigned_to', 'status']);
        });

        Schema::create('follow_ups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('assigned_to')->nullable()->constrained('users');
            $table->foreignId('proposal_submission_id')->nullable()->constrained('proposal_submissions')->nullOnDelete();
            $table->foreignId('opportunity_id')->nullable()->constrained('opportunities')->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->string('status')->default('scheduled');
            $table->string('type')->default('email');
            $table->string('subject')->nullable();
            $table->text('message')->nullable();
            $table->date('scheduled_date')->nullable()->index();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->boolean('is_automated')->default(false);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['assigned_to', 'status']);
            $table->index(['scheduled_date', 'status']);
        });

        Schema::create('follow_up_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('proposal_submission_id')->nullable()->constrained('proposal_submissions')->nullOnDelete();
            $table->string('name');
            $table->json('intervals_days')->comment('Array of days after submission to trigger follow-up');
            $table->string('template_type')->default('email');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->morphs('remindable');
            $table->string('message');
            $table->timestamp('remind_at');
            $table->boolean('is_sent')->default(false);
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->index(['remind_at', 'is_sent']);
        });

        // Commission tables
        Schema::create('commission_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('type');
            $table->decimal('rate', 8, 4)->nullable()->comment('Percentage rate 0-100');
            $table->decimal('fixed_amount', 18, 2)->nullable();
            $table->string('base_on')->default('award_value')->comment('proposal_value|award_value|margin');
            $table->json('tier_config')->nullable()->comment('For tiered commissions');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('commissions', function (Blueprint $table) {
            $table->id();
            $table->string('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('proposal_submission_id')->constrained('proposal_submissions');
            $table->foreignId('commission_rule_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('calculated_by')->nullable()->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->string('type');
            $table->decimal('base_amount', 18, 2);
            $table->decimal('rate', 8, 4)->nullable();
            $table->decimal('commission_amount', 18, 2);
            $table->string('status')->default('calculated');
            $table->string('period_month', 7)->nullable()->comment('YYYY-MM');
            $table->text('notes')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'period_month']);
        });

        Schema::create('commission_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('adjusted_by')->constrained('users');
            $table->decimal('amount', 18, 2);
            $table->string('reason');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('commission_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('period_label');
            $table->string('period_month', 7);
            $table->string('status')->default('open');
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_periods');
        Schema::dropIfExists('commission_adjustments');
        Schema::dropIfExists('commissions');
        Schema::dropIfExists('commission_rules');
        Schema::dropIfExists('reminders');
        Schema::dropIfExists('follow_up_schedules');
        Schema::dropIfExists('follow_ups');
        Schema::dropIfExists('tasks');
    }
};
