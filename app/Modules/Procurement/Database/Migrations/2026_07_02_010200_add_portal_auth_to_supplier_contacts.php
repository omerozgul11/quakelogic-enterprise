<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vendor-portal login for supplier contacts. A contact can be granted a portal
 * password so the vendor can sign in to a read-only self-service portal and see
 * their own POs, quotations, and bills. Additive; portal access is off until a
 * password is provisioned by staff. Passwords are bcrypt-hashed, never stored
 * in plain text.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procurement_supplier_contacts', function (Blueprint $table) {
            $table->boolean('portal_enabled')->default(false)->after('is_primary');
            $table->string('portal_password')->nullable()->after('portal_enabled');
            $table->timestamp('portal_last_login_at')->nullable()->after('portal_password');
        });
    }

    public function down(): void
    {
        Schema::table('procurement_supplier_contacts', function (Blueprint $table) {
            $table->dropColumn(['portal_enabled', 'portal_password', 'portal_last_login_at']);
        });
    }
};
