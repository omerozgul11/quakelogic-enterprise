<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Compliance is now admin-only. Revoke the compliance permissions from every
     * non-admin role; Super Admin retains them via its all-permissions sync.
     */
    public function up(): void
    {
        $roles = ['CEO', 'Business Development Manager', 'Proposal Manager', 'Proposal Writer', 'Sales Representative', 'Finance', 'Read Only'];
        foreach ($roles as $name) {
            $role = Role::where('name', $name)->first();
            if ($role) {
                $role->revokePermissionTo(array_filter(['view compliance', 'manage compliance'], fn ($p) => $role->hasPermissionTo($p)));
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Re-grant view compliance to the roles that previously had it.
        foreach (['CEO', 'Business Development Manager', 'Proposal Manager', 'Proposal Writer', 'Sales Representative', 'Finance', 'Read Only'] as $name) {
            Role::where('name', $name)->first()?->givePermissionTo('view compliance');
        }
        foreach (['CEO', 'Business Development Manager', 'Proposal Manager'] as $name) {
            Role::where('name', $name)->first()?->givePermissionTo('manage compliance');
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
