<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * RBAC for Phase 5 (Contracts) and Phase 7 (Compliance + Template library).
     * Mirrors the grants in RolesPermissionsSeeder so existing databases pick up
     * the new permissions without a full reseed.
     */
    public function up(): void
    {
        $new = [
            'view contracts', 'manage contracts',
            'view compliance', 'manage compliance', 'view templates', 'manage templates',
        ];
        foreach ($new as $name) {
            Permission::firstOrCreate(['name' => $name]);
        }

        $grants = [
            'Super Admin' => $new,
            'CEO' => $new,
            'Business Development Manager' => $new,
            'Proposal Manager' => $new,
            'Proposal Writer' => ['view contracts', 'view compliance', 'view templates', 'manage templates'],
            'Sales Representative' => ['view contracts', 'view compliance', 'view templates'],
            'Finance' => ['view contracts', 'manage contracts', 'view compliance'],
            'Read Only' => ['view contracts', 'view compliance', 'view templates'],
        ];

        foreach ($grants as $roleName => $perms) {
            $role = Role::where('name', $roleName)->first();
            if ($role) {
                $role->givePermissionTo($perms);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::whereIn('name', [
            'view contracts', 'manage contracts',
            'view compliance', 'manage compliance', 'view templates', 'manage templates',
        ])->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
