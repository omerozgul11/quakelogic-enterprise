<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-user SMTP credentials so each user can connect their own work email
 * (e.g. a Gmail/Workspace app password or Office 365) and have proposal
 * follow-ups & digests send from their own address. The password is stored
 * encrypted (see the EmailAccount model cast).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_accounts', function (Blueprint $table) {
            $table->string('smtp_host')->nullable()->after('email');
            $table->unsignedInteger('smtp_port')->nullable()->after('smtp_host');
            $table->string('smtp_encryption')->nullable()->after('smtp_port'); // tls | ssl | null
            $table->string('smtp_username')->nullable()->after('smtp_encryption');
            $table->text('smtp_password')->nullable()->after('smtp_username');
            $table->string('from_name')->nullable()->after('smtp_password');
        });
    }

    public function down(): void
    {
        Schema::table('email_accounts', function (Blueprint $table) {
            $table->dropColumn(['smtp_host', 'smtp_port', 'smtp_encryption', 'smtp_username', 'smtp_password', 'from_name']);
        });
    }
};
