<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Adds the `manage all projects` admin capability for the Project Management
 * upgrade. Roles WITHOUT it see only the projects they're assigned to (owner,
 * project manager or team member); roles WITH it (admins/executives) see and
 * manage every project in the organization and reach the admin settings.
 *
 * `manage projects` (already seeded for the CRM section) still gates creating a
 * project and managing one you lead. Mirrors the module pattern: direct pivot
 * inserts so the permission cache isn't tripped mid-migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        $permId = DB::table('permissions')->where('name', 'manage all projects')->value('id');
        if (! $permId) {
            $permId = DB::table('permissions')->insertGetId([
                'name' => 'manage all projects',
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Grant to the admin/executive roles. Super Admin already holds every
        // permission via the seeder's Permission::all() sync, but we add it here
        // too so a DB that hasn't re-seeded still gets it.
        $roles = DB::table('roles')
            ->whereIn('name', ['Super Admin', 'CEO', 'Business Development Manager', 'Proposal Manager'])
            ->pluck('id');

        foreach ($roles as $roleId) {
            DB::table('role_has_permissions')->updateOrInsert([
                'permission_id' => $permId,
                'role_id' => $roleId,
            ]);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        $permId = DB::table('permissions')->where('name', 'manage all projects')->value('id');
        if ($permId) {
            DB::table('role_has_permissions')->where('permission_id', $permId)->delete();
            DB::table('permissions')->where('id', $permId)->delete();
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
