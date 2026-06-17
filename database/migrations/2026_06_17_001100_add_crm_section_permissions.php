<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * RBAC for the dedicated /crm section. `access crm` gates the whole section
     * (mirrors `access shipments`) and is granted to EVERY role so all Proposals
     * users can reach the CRM. `manage *` permissions gate writes per module.
     * Mirrors the grants added to RolesPermissionsSeeder so existing databases
     * pick up the new permissions without a full reseed.
     */
    public function up(): void
    {
        $new = ['access crm', 'manage leads', 'manage projects', 'manage invoices'];
        foreach ($new as $name) {
            Permission::firstOrCreate(['name' => $name]);
        }

        $manageAll = ['access crm', 'manage leads', 'manage projects', 'manage invoices'];

        $grants = [
            'Super Admin' => $manageAll,
            'CEO' => $manageAll,
            'Business Development Manager' => $manageAll,
            'Proposal Manager' => $manageAll,
            'Proposal Writer' => ['access crm'],
            'Sales Representative' => ['access crm', 'manage leads', 'manage projects'],
            // Finance also gets `view crm` so it can read CRM data while owning invoices.
            'Finance' => ['access crm', 'view crm', 'manage invoices'],
            'Read Only' => ['access crm'],
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
        Permission::whereIn('name', ['access crm', 'manage leads', 'manage projects', 'manage invoices'])->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
