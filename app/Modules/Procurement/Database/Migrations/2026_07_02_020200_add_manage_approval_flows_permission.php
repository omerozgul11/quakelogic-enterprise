<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Permission to configure approval chains. Config-level, so granted only to the
 * admin/leadership/finance roles; actually *approving* a step is governed by the
 * step's own approver assignment (a specific user or role), not this permission.
 * Direct pivot inserts — Role::givePermissionTo trips the lazy-loading guard in a
 * migration.
 */
return new class extends Migration
{
    private string $name = 'manage approval flows';

    private array $roles = ['Super Admin', 'CEO', 'Finance', 'Contracts & Billing Administrator'];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permId = Permission::firstOrCreate(['name' => $this->name, 'guard_name' => 'web'])->id;

        foreach (DB::table('roles')->whereIn('name', $this->roles)->get(['id']) as $role) {
            DB::table('role_has_permissions')->updateOrInsert([
                'permission_id' => $permId,
                'role_id' => $role->id,
            ]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $id = DB::table('permissions')->where('name', $this->name)->value('id');
        if ($id) {
            DB::table('role_has_permissions')->where('permission_id', $id)->delete();
            DB::table('model_has_permissions')->where('permission_id', $id)->delete();
            DB::table('permissions')->where('id', $id)->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
