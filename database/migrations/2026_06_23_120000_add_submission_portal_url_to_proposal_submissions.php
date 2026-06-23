<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proposal_submissions', function (Blueprint $table) {
            // Where to submit when 'portal' is one of the submission methods —
            // the buyer's e-submission portal URL.
            $table->string('submission_portal_url', 2048)->nullable()->after('submission_methods');
        });
    }

    public function down(): void
    {
        Schema::table('proposal_submissions', function (Blueprint $table) {
            $table->dropColumn('submission_portal_url');
        });
    }
};
