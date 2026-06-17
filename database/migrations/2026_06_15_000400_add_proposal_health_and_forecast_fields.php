<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 2 (Proposal Health & Follow-Up Control) + Phase 4 (Revenue
     * Forecasting) fields:
     *  - last_client_contact_at: drives the health "traffic light" and the
     *    no-contact escalation ladder.
     *  - health_escalation_level: highest escalation tier (0/30/45/60 days)
     *    already alerted on, so the daily job never re-spams a tier; reset to 0
     *    whenever fresh client contact is logged.
     *  - win_probability: 0–100 (%) for weighted pipeline forecasting.
     *  - expected_award_date: forecast award date for revenue projections.
     */
    public function up(): void
    {
        Schema::table('proposal_submissions', function (Blueprint $table) {
            $table->timestamp('last_client_contact_at')->nullable()->after('submission_date');
            $table->unsignedTinyInteger('health_escalation_level')->default(0)->after('last_client_contact_at');
            $table->unsignedTinyInteger('win_probability')->nullable()->after('award_value');
            $table->date('expected_award_date')->nullable()->after('award_date');
        });

        // Seed last_client_contact_at from the most recent client-facing follow-up
        // (one tied to an external contact that was actually sent or responded to).
        $latest = DB::table('follow_ups')
            ->select('proposal_submission_id', DB::raw('MAX(COALESCE(responded_at, sent_at, created_at)) as last_at'))
            ->whereNotNull('proposal_submission_id')
            ->whereNotNull('contact_id')
            ->groupBy('proposal_submission_id')
            ->get();

        foreach ($latest as $row) {
            DB::table('proposal_submissions')
                ->where('id', $row->proposal_submission_id)
                ->update(['last_client_contact_at' => $row->last_at]);
        }
    }

    public function down(): void
    {
        Schema::table('proposal_submissions', function (Blueprint $table) {
            $table->dropColumn(['last_client_contact_at', 'health_escalation_level', 'win_probability', 'expected_award_date']);
        });
    }
};
