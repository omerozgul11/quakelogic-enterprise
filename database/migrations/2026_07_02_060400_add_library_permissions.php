<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Permissions for the Document Library. Everyone with app access can view the
 * shared library; everyone except Read Only can upload/organise/link (manage).
 * Direct pivot inserts — Role::givePermissionTo trips the lazy-loading guard
 * inside a migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $view = Permission::firstOrCreate(['name' => 'view library', 'guard_name' => 'web'])->id;
        $manage = Permission::firstOrCreate(['name' => 'manage library', 'guard_name' => 'web'])->id;

        foreach (DB::table('roles')->get(['id', 'name']) as $role) {
            DB::table('role_has_permissions')->updateOrInsert([
                'permission_id' => $view,
                'role_id' => $role->id,
            ]);
            if ($role->name !== 'Read Only') {
                DB::table('role_has_permissions')->updateOrInsert([
                    'permission_id' => $manage,
                    'role_id' => $role->id,
                ]);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $ids = DB::table('permissions')->whereIn('name', ['view library', 'manage library'])->pluck('id');
        DB::table('role_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('model_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
