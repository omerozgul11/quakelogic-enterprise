<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Classify each submission as an RFI, RFQ, RFP or full Proposal so the type
     * is visible on the detail page and filterable in the list. Existing rows
     * default to 'proposal'.
     */
    public function up(): void
    {
        Schema::table('proposal_submissions', function (Blueprint $table) {
            $table->string('proposal_type', 20)->default('proposal')->after('project_name')->index();
        });
    }

    public function down(): void
    {
        Schema::table('proposal_submissions', function (Blueprint $table) {
            $table->dropColumn('proposal_type');
        });
    }
};
