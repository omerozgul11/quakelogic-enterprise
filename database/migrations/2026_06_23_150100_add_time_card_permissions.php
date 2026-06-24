<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * `manage all time cards` lets a role view AND edit every user's clocked
     * time (the cross-user filter on the Time Cards page). Everyone else — gated
     * only by `access crm` — can still clock in/out and see/edit their own.
     * Granted to Super Admin only, per product decision.
     *
     * Uses direct pivot inserts (not Role::givePermissionTo) so it works under
     * preventLazyLoading inside a migration, matching the module permission
     * migrations already in this codebase.
     */
    public function up(): void
    {
        $permId = Permission::firstOrCreate(['name' => 'manage all time cards', 'guard_name' => 'web'])->id;

        $roleId = DB::table('roles')->where('name', 'Super Admin')->value('id');
        if ($roleId) {
            DB::table('role_has_permissions')->updateOrInsert([
                'permission_id' => $permId,
                'role_id' => $roleId,
            ]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $permId = DB::table('permissions')->where('name', 'manage all time cards')->value('id');
        if ($permId) {
            DB::table('role_has_permissions')->where('permission_id', $permId)->delete();
            DB::table('permissions')->where('id', $permId)->delete();
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
