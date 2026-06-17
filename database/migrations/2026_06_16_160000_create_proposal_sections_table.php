<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Final, user-edited proposal narrative sections (from the AI Proposal
     * Writer). These are assembled, in order, into the Word/PDF export. One row
     * per (proposal, section_key) — saving a section upserts it.
     */
    public function up(): void
    {
        Schema::create('proposal_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('proposal_submission_id')->constrained()->cascadeOnDelete();
            $table->string('section_key', 60);
            $table->string('heading');
            $table->longText('content');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['proposal_submission_id', 'section_key']);
            $table->index('organization_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proposal_sections');
    }
};
