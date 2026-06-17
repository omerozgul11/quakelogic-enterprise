<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * The CRM section should be reachable by EVERY user. Beyond the standard
     * seeded roles, organizations create their own custom roles at runtime
     * (e.g. "Contracts & Billing Administrator") which the earlier grant did not
     * cover. Grant `access crm` to every role that currently exists.
     *
     * Written against the pivot table directly: the app runs with lazy-loading
     * prevention on, so Spatie's givePermissionTo() (which touches the role's
     * permissions relation) would throw inside a migration.
     */
    public function up(): void
    {
        $permission = Permission::firstOrCreate(['name' => 'access crm', 'guard_name' => 'web']);

        // Standard Spatie pivot (table `role_has_permissions`, columns
        // permission_id / role_id). Insert directly to stay clear of the
        // lazy-loading guard that trips Spatie's relation-based helpers.
        foreach (Role::pluck('id') as $roleId) {
            DB::table('role_has_permissions')->updateOrInsert([
                'permission_id' => $permission->id,
                'role_id' => $roleId,
            ]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // No-op: leaving `access crm` granted is harmless, and reversing it could
        // strip access an admin has since curated.
    }
};
