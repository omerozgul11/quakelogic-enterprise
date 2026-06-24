<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Expense Tracker permissions + role grants (direct pivot inserts — the
 * lazy-loading guard trips Role::givePermissionTo inside a migration).
 *
 *   - "access expenses" + "view expenses" → every role
 *   - "manage expenses" (create/edit/approve/reimburse) → every role except Read Only.
 *
 * Idempotent: re-running firstOrCreate + updateOrInsert never duplicates or wipes.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $all = ['access expenses', 'view expenses', 'manage expenses'];
        $viewOnly = ['access expenses', 'view expenses'];

        $permIds = [];
        foreach ($all as $name) {
            $permIds[$name] = Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web'])->id;
        }

        foreach (DB::table('roles')->get(['id', 'name']) as $role) {
            $grants = $role->name === 'Read Only' ? $viewOnly : $all;
            foreach ($grants as $name) {
                DB::table('role_has_permissions')->updateOrInsert([
                    'permission_id' => $permIds[$name],
                    'role_id' => $role->id,
                ]);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $names = ['access expenses', 'view expenses', 'manage expenses'];
        $ids = DB::table('permissions')->whereIn('name', $names)->pluck('id');

        DB::table('role_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('model_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
