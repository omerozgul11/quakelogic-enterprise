<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chronological activity feed for CRM records (leads, clients, contacts). Each
 * row is one human-readable timeline entry — a logged call/email/meeting/note,
 * or a system event (created, stage changed, converted). Polymorphic `subject`
 * so the same feed attaches to any CRM record. Additive; nothing existing
 * changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_activities', function (Blueprint $table) {
            $table->id();
            $table->string('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            // The record this entry belongs to (Lead / Company / Contact).
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');
            // Who did it; null = system / automation.
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            // note | call | email | meeting | stage_change | created | converted | task | system
            $table->string('type', 30)->default('note');
            $table->text('body')->nullable();
            // Structured extras (from/to stage, follow-up id, automation id, …).
            $table->json('meta')->nullable();
            // When the activity actually happened (may differ from created_at for
            // back-dated logs); defaults to now via the app.
            $table->timestamp('happened_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'subject_type', 'subject_id', 'happened_at'], 'crm_activities_subject_idx');
            $table->index(['organization_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_activities');
    }
};
