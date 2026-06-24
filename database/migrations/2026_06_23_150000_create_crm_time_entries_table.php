<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_time_entries', function (Blueprint $table) {
            $table->id();
            $table->string('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            // Whose shift this is.
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // Who recorded it (self for live punches; an admin for corrections).
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('clock_in');
            // Null while the user is still clocked in (open shift).
            $table->timestamp('clock_out')->nullable();
            $table->string('note', 500)->nullable();
            // How the entry was created: a live clock punch or a manual entry.
            $table->string('source', 20)->default('clock');

            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'user_id', 'clock_in']);
            $table->index(['organization_id', 'clock_in']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_time_entries');
    }
};
