<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-organization QuickBooks Online connection (OAuth tokens + sync state).
 * Additive only — a brand-new table. Tokens are stored encrypted (model cast).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quickbooks_connections', function (Blueprint $table) {
            $table->id();
            $table->string('ulid')->unique();
            $table->foreignId('organization_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('connected_by')->constrained('users');

            $table->string('realm_id');                       // QuickBooks company id
            $table->string('environment', 20)->default('production'); // sandbox|production
            $table->text('access_token')->nullable();         // encrypted (model cast)
            $table->text('refresh_token')->nullable();        // encrypted (model cast)
            $table->timestamp('token_expires_at')->nullable();
            $table->timestamp('refresh_token_expires_at')->nullable();

            $table->boolean('is_demo')->default(false);       // fake/demo connection (no live Intuit link)
            $table->boolean('push_enabled')->default(false);  // allow pushing our expenses into QuickBooks
            $table->string('push_account_id')->nullable();    // QBO bank/credit account a pushed Purchase is paid from
            $table->string('push_expense_account_id')->nullable(); // QBO expense account for the line

            $table->timestamp('last_synced_at')->nullable();
            $table->string('last_sync_status', 20)->nullable(); // ok|error
            $table->string('last_sync_message', 500)->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quickbooks_connections');
    }
};
