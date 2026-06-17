<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-user state on an opportunity: the daily-digest reaction (Save for Later /
 * Interested / In Progress / Not Interested / Already Submitted / Needs Review)
 * and the AI match score + recommendation (primary/secondary). One row per
 * (opportunity, user). Match scoring (Phase 2) writes match_score/is_recommended;
 * the digest and pipeline read them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opportunity_user_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('opportunity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('reaction')->nullable();
            $table->decimal('match_score', 5, 2)->nullable();
            $table->json('match_reasons')->nullable();
            $table->boolean('is_recommended')->default(false);
            $table->string('recommended_role')->nullable(); // primary | secondary
            $table->timestamp('reacted_at')->nullable();
            $table->timestamps();

            $table->unique(['opportunity_id', 'user_id']);
            $table->index(['organization_id', 'user_id', 'reaction'], 'ous_org_user_reaction_idx');
            $table->index(['organization_id', 'user_id', 'is_recommended'], 'ous_org_user_recommended_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opportunity_user_states');
    }
};
