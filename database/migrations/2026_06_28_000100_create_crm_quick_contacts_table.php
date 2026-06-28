<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quick Contacts — a shared org rolodex of frequently-dialed numbers that aren't
 * CRM people (e.g. "Chase Wire Transfer Department"). Purely additive; lives
 * alongside, and is independent of, the `contacts` table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_quick_contacts', function (Blueprint $table) {
            $table->id();
            $table->string('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            // Who added it; kept for audit even if they leave.
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('name', 150);
            // The entity behind the desk, e.g. "Chase Bank". Optional.
            $table->string('organization_name', 150)->nullable();
            // banking | shipping | vendor | government | insurance | support | internal | other
            $table->string('category', 30)->default('other');

            $table->string('phone', 40)->nullable();
            $table->string('extension', 20)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('website', 255)->nullable();
            $table->string('notes', 1000)->nullable();

            // Pinned contacts float to the top of the list.
            $table->boolean('is_pinned')->default(false);
            $table->integer('sort_order')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'category']);
            $table->index(['organization_id', 'is_pinned']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_quick_contacts');
    }
};
