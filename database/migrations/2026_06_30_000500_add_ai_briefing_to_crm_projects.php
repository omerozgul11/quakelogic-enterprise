<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 7 of the Project Field Information System: store the AI-generated field
 * briefing on the project so it shows on the Overview and in the Field Packet
 * without re-calling the model each view. Purely additive columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_projects', function (Blueprint $table) {
            $table->longText('ai_briefing')->nullable()->after('specs');
            $table->timestamp('ai_briefing_generated_at')->nullable()->after('ai_briefing');
            $table->foreignId('ai_briefing_by')->nullable()->after('ai_briefing_generated_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('crm_projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ai_briefing_by');
            $table->dropColumn(['ai_briefing', 'ai_briefing_generated_at']);
        });
    }
};
