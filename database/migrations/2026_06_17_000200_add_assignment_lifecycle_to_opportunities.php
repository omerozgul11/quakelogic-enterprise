<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Opportunity Assignment module — an assignment/ownership LIFECYCLE that runs
 * alongside the existing pipeline `status` (which still tracks BD pursuit
 * state). This lifecycle drives accountability: who owns it, has it been
 * claimed/locked, and how long since it was assigned or last touched.
 *
 *  - assignment_stage: unassigned → assigned → accepted → in_progress →
 *    proposal_drafting → under_review → submitted → won/lost/abandoned.
 *  - assigned_at / accepted_at: aging clocks for the escalation ladder.
 *  - last_activity_at: "days since last activity" for the health score.
 *  - ownership_locked: set when a user claims an opportunity ("In Progress");
 *    other users may view but cannot claim without admin approval.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opportunities', function (Blueprint $table) {
            $table->string('assignment_stage')->default('unassigned')->after('status');
            $table->timestamp('assigned_at')->nullable()->after('assignment_stage');
            $table->timestamp('accepted_at')->nullable()->after('assigned_at');
            $table->timestamp('last_activity_at')->nullable()->after('accepted_at');
            $table->boolean('ownership_locked')->default(false)->after('last_activity_at');
            $table->timestamp('ownership_locked_at')->nullable()->after('ownership_locked');

            $table->index(['organization_id', 'assignment_stage']);
            $table->index(['organization_id', 'last_activity_at']);
        });

        // Backfill: seed last_activity_at from updated_at, and reflect existing
        // ownership/assignment so aging/escalation start from a sane baseline.
        DB::table('opportunities')->update(['last_activity_at' => DB::raw('updated_at')]);
        DB::table('opportunities')->whereNotNull('owner_id')->update([
            'assignment_stage' => 'assigned',
            'assigned_at' => DB::raw('created_at'),
        ]);
    }

    public function down(): void
    {
        Schema::table('opportunities', function (Blueprint $table) {
            $table->dropIndex(['organization_id', 'assignment_stage']);
            $table->dropIndex(['organization_id', 'last_activity_at']);
            $table->dropColumn([
                'assignment_stage', 'assigned_at', 'accepted_at', 'last_activity_at',
                'ownership_locked', 'ownership_locked_at',
            ]);
        });
    }
};
