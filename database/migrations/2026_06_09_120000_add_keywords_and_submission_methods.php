<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Private, per-user opportunity keyword filters.
            $table->json('pipeline_keywords')->nullable()->after('notification_preferences');
        });

        Schema::table('proposal_submissions', function (Blueprint $table) {
            // How the proposal is/was submitted — may be multiple (mail, email, portal).
            $table->json('submission_methods')->nullable()->after('submission_channel');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('pipeline_keywords');
        });
        Schema::table('proposal_submissions', function (Blueprint $table) {
            $table->dropColumn('submission_methods');
        });
    }
};
