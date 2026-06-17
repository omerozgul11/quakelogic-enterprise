<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Collaborative proposal editing: every user may view + edit every existing
     * proposal. Grants the proposal view/edit permissions to ALL roles (the
     * ProposalSubmissionPolicy no longer restricts edit to owner/team). Pivot
     * inserts avoid the lazy-loading guard that trips Spatie's relation helpers
     * inside migrations.
     */
    public function up(): void
    {
        $names = [
            'view proposals', 'view all proposals', 'update proposals',
            'view private proposal details', 'manage proposal files',
        ];

        $permIds = [];
        foreach ($names as $name) {
            $permIds[] = Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web'])->id;
        }

        foreach (Role::pluck('id') as $roleId) {
            foreach ($permIds as $permId) {
                DB::table('role_has_permissions')->updateOrInsert([
                    'permission_id' => $permId,
                    'role_id' => $roleId,
                ]);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // No-op: leaving the grants in place is harmless.
    }
};
