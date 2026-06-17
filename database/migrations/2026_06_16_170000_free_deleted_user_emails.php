<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Free the email addresses held by already soft-deleted users so they can be
     * reused for new accounts. The users.email column is hard-unique and the
     * deleted rows are kept for audit, so without this the live unique index (and
     * the unique validation rule) block re-creating a user with a deleted user's
     * email. Going forward, deleteUser() tombstones the email on delete; this
     * backfills the ones deleted before that. Idempotent (skips already-freed).
     */
    public function up(): void
    {
        DB::table('users')
            ->whereNotNull('deleted_at')
            ->where('email', 'not like', 'deleted+%')
            ->update(['email' => DB::raw("LEFT(CONCAT('deleted+', id, '+', email), 255)")]);
    }

    public function down(): void
    {
        // Irreversible: the original addresses are intentionally released.
    }
};
