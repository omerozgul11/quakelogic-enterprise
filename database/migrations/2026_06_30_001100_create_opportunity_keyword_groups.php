<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin-editable keyword groups used to score opportunities for QuakeLogic
 * relevance, plus the scoring result columns on opportunities. Applies to every
 * opportunity source (BidPrime email, SAM.gov, manual). Purely additive.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opportunity_keyword_groups', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('name');
            $table->json('keywords');                 // array of phrases
            $table->json('naics_codes')->nullable();  // array of NAICS that count as a fit
            $table->unsignedInteger('weight')->default(10);
            $table->boolean('is_exclusion')->default(false); // matches → mark Not Relevant
            $table->boolean('is_active')->default(true);
            $table->string('color')->nullable();
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'is_active'], 'okg_org_active_idx');
        });

        Schema::table('opportunities', function (Blueprint $table) {
            $table->unsignedSmallInteger('relevance_score')->nullable()->after('matched_keywords');
            $table->string('priority')->nullable()->after('relevance_score');
            $table->json('score_breakdown')->nullable()->after('priority');
            $table->index(['organization_id', 'priority'], 'opp_org_priority_idx');
        });
    }

    public function down(): void
    {
        Schema::table('opportunities', function (Blueprint $table) {
            $table->dropIndex('opp_org_priority_idx');
            $table->dropColumn(['relevance_score', 'priority', 'score_breakdown']);
        });

        Schema::dropIfExists('opportunity_keyword_groups');
    }
};
