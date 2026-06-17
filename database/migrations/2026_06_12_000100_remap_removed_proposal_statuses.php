<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The "Draft", "Under Review" and "Negotiation" statuses were removed from
     * ProposalStatus. Remap any existing proposals that still carry one of those
     * values so nothing references a status the enum no longer knows about, and
     * make "In Progress" the new starting default.
     */
    public function up(): void
    {
        DB::table('proposal_submissions')
            ->whereIn('status', ['draft', 'under_review'])
            ->update(['status' => 'in_progress']);

        DB::table('proposal_submissions')
            ->where('status', 'negotiation')
            ->update(['status' => 'pending']);

        DB::statement("ALTER TABLE proposal_submissions ALTER COLUMN status SET DEFAULT 'in_progress'");
    }

    public function down(): void
    {
        // The data remap is intentionally one-way; only restore the old default.
        DB::statement("ALTER TABLE proposal_submissions ALTER COLUMN status SET DEFAULT 'draft'");
    }
};
