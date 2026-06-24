<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Team leave / time-off — a date-range a member is away (vacation, sick, etc.).
 * Powers the "On leave" count on the CRM team-presence strip. Purely additive;
 * the live clock-in/out flow is untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_leaves', function (Blueprint $table) {
            $table->id();
            $table->string('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            // Who is on leave.
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // Who recorded it (a manager); kept for audit even if they leave.
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->date('start_date');
            $table->date('end_date');
            // vacation | sick | personal | other — free string, validated in the controller.
            $table->string('type', 30)->default('vacation');
            $table->string('note', 500)->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'start_date', 'end_date']);
            $table->index(['organization_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_leaves');
    }
};
