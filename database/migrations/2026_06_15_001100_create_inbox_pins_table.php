<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-user pinned Inbox conversations. Threads are derived (proposal /
     * direct / general), so a pin is stored by its stable thread key.
     */
    public function up(): void
    {
        Schema::create('inbox_pins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('thread_key');
            $table->timestamps();

            $table->unique(['user_id', 'thread_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inbox_pins');
    }
};
