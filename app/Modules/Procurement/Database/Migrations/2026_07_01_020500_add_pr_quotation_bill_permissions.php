<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Permissions for the new purchase-request / quotation / bill stages. Granted to
 * every role except Read Only (which keeps view-only access from the base
 * procurement permissions). Direct pivot inserts — Role::givePermissionTo trips
 * the lazy-loading guard inside a migration.
 */
return new class extends Migration
{
    private array $names = [
        'manage purchase requests',
        'approve purchase requests',
        'manage quotations',
        'manage bills',
        'approve bill payments',
    ];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permIds = [];
        foreach ($this->names as $name) {
            $permIds[$name] = Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web'])->id;
        }

        foreach (DB::table('roles')->get(['id', 'name']) as $role) {
            if ($role->name === 'Read Only') {
                continue;
            }
            foreach ($this->names as $name) {
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

        $ids = DB::table('permissions')->whereIn('name', $this->names)->pluck('id');
        DB::table('role_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('model_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
