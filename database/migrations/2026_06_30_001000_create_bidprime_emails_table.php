<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BidPrime email ingestion: stores each BidPrime alert email read from the Gmail
 * inbox (raw HTML/text + Gmail identifiers + parse status) so every imported
 * opportunity traces back to its source email, and emails can be re-parsed.
 *
 * Purely additive. The existing API-based bidprime_imports/items tables are
 * reused for the per-opportunity upsert log; we add a link from an import item
 * back to the email it came from.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bidprime_emails', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bidprime_import_id')->nullable()->constrained('bidprime_imports')->nullOnDelete();

            $table->string('gmail_message_id')->nullable();  // RFC Message-ID header (stable across fetches)
            $table->string('gmail_uid')->nullable();         // IMAP UID within the mailbox
            $table->string('thread_id')->nullable();
            $table->string('from_email')->nullable();
            $table->string('from_name')->nullable();
            $table->string('subject', 1000)->nullable();
            $table->timestamp('received_at')->nullable();

            $table->longText('raw_html')->nullable();
            $table->longText('raw_text')->nullable();

            // pending | parsed | no_opportunities | failed | skipped
            $table->string('status')->default('pending');
            $table->unsignedInteger('opportunities_found')->default(0);
            $table->text('parse_error')->nullable();
            $table->timestamp('processed_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // One stored copy per Gmail message per org (idempotent re-fetch).
            $table->unique(['organization_id', 'gmail_message_id'], 'bpe_org_msg_unique');
            $table->index(['organization_id', 'status'], 'bpe_org_status_idx');
        });

        Schema::table('bidprime_import_items', function (Blueprint $table) {
            $table->foreignId('bidprime_email_id')->nullable()->after('bidprime_import_id')
                ->constrained('bidprime_emails')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bidprime_import_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bidprime_email_id');
        });

        Schema::dropIfExists('bidprime_emails');
    }
};
