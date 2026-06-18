<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Inventory module permissions + role grants. Uses direct pivot inserts (not
 * Role::givePermissionTo) because Model::preventLazyLoading() is active and the
 * Spatie relation path trips the lazy-loading guard inside a migration.
 *
 *   - "access inventory" + "view inventory"  → every role (mirrors "access crm")
 *   - "manage products" / "manage warehouses" / "adjust stock" → every role
 *     except Read Only.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $all = ['access inventory', 'view inventory', 'manage products', 'manage warehouses', 'adjust stock'];
        $viewOnly = ['access inventory', 'view inventory'];

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

        $names = ['access inventory', 'view inventory', 'manage products', 'manage warehouses', 'adjust stock'];
        $ids = DB::table('permissions')->whereIn('name', $names)->pluck('id');

        DB::table('role_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('model_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
