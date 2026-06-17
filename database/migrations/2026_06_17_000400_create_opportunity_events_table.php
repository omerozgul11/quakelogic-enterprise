<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Immutable opportunity timeline: discovery, assignment, claim, reactions,
 * stage/status changes, notes, emails, meetings, file uploads, reviews,
 * submission, award decisions, escalations and reassignments. Append-only —
 * rows are never updated or deleted, so the full history is always preserved.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opportunity_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('opportunity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type')->index();
            $table->string('description', 1024);
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['opportunity_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opportunity_events');
    }
};
