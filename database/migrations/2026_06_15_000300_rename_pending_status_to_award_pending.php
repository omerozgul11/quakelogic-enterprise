<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The proposal "pending" status is renamed to "award_pending" (label
     * "Award Pending"). A distinct value is used — rather than just relabelling
     * "pending" — so it never collides with the unrelated "pending" status on
     * commissions, AI jobs, and exports. Only proposal tables are touched.
     */
    public function up(): void
    {
        DB::table('proposal_submissions')->where('status', 'pending')->update(['status' => 'award_pending']);
        DB::table('proposal_status_history')->where('from_status', 'pending')->update(['from_status' => 'award_pending']);
        DB::table('proposal_status_history')->where('to_status', 'pending')->update(['to_status' => 'award_pending']);
    }

    public function down(): void
    {
        DB::table('proposal_submissions')->where('status', 'award_pending')->update(['status' => 'pending']);
        DB::table('proposal_status_history')->where('from_status', 'award_pending')->update(['from_status' => 'pending']);
        DB::table('proposal_status_history')->where('to_status', 'award_pending')->update(['to_status' => 'pending']);
    }
};
