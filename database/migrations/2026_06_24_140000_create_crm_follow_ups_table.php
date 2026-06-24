<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CRM follow-up tasks — a dated, assignable reminder ("call back Tue", "send
 * quote"). Optionally hangs off a Lead / Company / Contact via the polymorphic
 * subject, or stands alone. Distinct from project tasks (crm_tasks) and proposal
 * follow-ups (follow_ups). Additive; nothing existing changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_follow_ups', function (Blueprint $table) {
            $table->id();
            $table->string('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();

            // Optional record this follow-up is about.
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();

            $table->string('title');
            $table->text('notes')->nullable();
            $table->date('due_date');
            $table->string('priority', 10)->default('normal'); // low | normal | high
            $table->string('status', 10)->default('open');      // open | done
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            // Guards the daily reminder against double-sending within one day.
            $table->date('reminded_on')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'assigned_to', 'status', 'due_date'], 'crm_follow_ups_queue_idx');
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_follow_ups');
    }
};
