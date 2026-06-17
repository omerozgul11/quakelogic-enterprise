<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Align the follow_up_schedules table with the FollowUpSchedule model and the
 * follow-ups:generate command, which were written against trigger-based columns
 * that the original migration never created. Without these, the command errored
 * against the real schema and schedule-driven follow-ups never generated.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('follow_up_schedules', function (Blueprint $table) {
            $table->string('trigger_event')->default('proposal_submitted')->after('name');
            $table->unsignedInteger('delay_days')->default(0)->after('trigger_event');
            $table->string('follow_up_type')->default('reminder')->after('delay_days');
            $table->string('subject_template')->nullable()->after('follow_up_type');
            $table->text('message_template')->nullable()->after('subject_template');
            $table->boolean('assign_to_owner')->default(true)->after('is_active');
            $table->foreignId('assign_to_user_id')->nullable()->after('assign_to_owner')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('follow_up_schedules', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assign_to_user_id');
            $table->dropColumn([
                'trigger_event', 'delay_days', 'follow_up_type',
                'subject_template', 'message_template', 'assign_to_owner',
            ]);
        });
    }
};
