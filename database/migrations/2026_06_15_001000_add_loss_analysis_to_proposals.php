<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 19 — Loss Analysis & Protest Evaluation. Capture why a bid was lost,
     * who won and at what price, whether a debrief / protest is warranted, and an
     * AI-generated loss assessment. (loss_reason + lessons_learned already exist.)
     */
    public function up(): void
    {
        Schema::table('proposal_submissions', function (Blueprint $table) {
            $table->string('loss_competitor')->nullable()->after('loss_reason');
            $table->decimal('loss_competitor_price', 18, 2)->nullable()->after('loss_competitor');
            $table->boolean('debrief_requested')->default(false)->after('loss_competitor_price');
            $table->boolean('protest_recommended')->default(false)->after('debrief_requested');
            $table->text('loss_assessment')->nullable()->after('protest_recommended');
        });
    }

    public function down(): void
    {
        Schema::table('proposal_submissions', function (Blueprint $table) {
            $table->dropColumn(['loss_competitor', 'loss_competitor_price', 'debrief_requested', 'protest_recommended', 'loss_assessment']);
        });
    }
};
