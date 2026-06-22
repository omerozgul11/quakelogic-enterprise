<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Adds the `impersonate users` permission (admin "Login as"). Granted to Super
 * Admin only — the impersonate route is also role:Super Admin gated, and the
 * service re-checks this permission (defense in depth).
 */
return new class extends Migration
{
    public function up(): void
    {
        $permId = DB::table('permissions')->where('name', 'impersonate users')->value('id');
        if (! $permId) {
            $permId = DB::table('permissions')->insertGetId([
                'name' => 'impersonate users',
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $superAdminId = DB::table('roles')->where('name', 'Super Admin')->value('id');
        if ($superAdminId) {
            DB::table('role_has_permissions')->updateOrInsert([
                'permission_id' => $permId,
                'role_id' => $superAdminId,
            ]);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        $permId = DB::table('permissions')->where('name', 'impersonate users')->value('id');
        if ($permId) {
            DB::table('role_has_permissions')->where('permission_id', $permId)->delete();
            DB::table('permissions')->where('id', $permId)->delete();
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
