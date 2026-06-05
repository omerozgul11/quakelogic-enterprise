<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            // Admin
            'manage users', 'manage roles', 'manage permissions', 'manage integrations',
            'view audit logs', 'manage system settings',

            // Opportunities
            'view opportunities', 'create opportunities', 'update opportunities', 'delete opportunities',
            'import opportunities', 'assign opportunities', 'qualify opportunities', 'make go no go decision',

            // Capture
            'view capture plans', 'manage capture plans', 'manage capture risks', 'manage capture decisions',

            // Proposals
            'view proposals', 'view all proposals', 'create proposals', 'update proposals', 'delete proposals',
            'submit proposals', 'approve proposals', 'manage proposal files', 'view private proposal details',

            // CRM
            'view crm', 'manage agencies', 'manage companies', 'manage contacts', 'manage activities',

            // Follow-ups
            'view follow ups', 'manage follow ups', 'send follow up emails',

            // Commissions
            'view own commissions', 'view all commissions', 'manage commission rules',
            'approve commissions', 'export commissions',

            // Reports
            'view dashboards', 'view executive dashboard', 'export reports',

            // AI
            'use ai assistant', 'run document extraction', 'review ai extraction', 'manage ai settings',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Super Admin - all permissions
        $superAdmin = Role::firstOrCreate(['name' => 'Super Admin']);
        $superAdmin->syncPermissions(Permission::all());

        // CEO
        $ceo = Role::firstOrCreate(['name' => 'CEO']);
        $ceo->syncPermissions([
            'view opportunities', 'create opportunities', 'update opportunities', 'delete opportunities',
            'import opportunities', 'assign opportunities', 'qualify opportunities', 'make go no go decision',
            'view capture plans', 'manage capture plans',
            'view proposals', 'view all proposals', 'create proposals', 'update proposals',
            'submit proposals', 'approve proposals', 'manage proposal files', 'view private proposal details',
            'view crm', 'manage agencies', 'manage companies', 'manage contacts', 'manage activities',
            'view follow ups', 'manage follow ups', 'send follow up emails',
            'view own commissions', 'view all commissions', 'manage commission rules', 'approve commissions', 'export commissions',
            'view dashboards', 'view executive dashboard', 'export reports',
            'use ai assistant', 'run document extraction', 'review ai extraction',
        ]);

        // Business Development Manager
        $bdm = Role::firstOrCreate(['name' => 'Business Development Manager']);
        $bdm->syncPermissions([
            'view opportunities', 'create opportunities', 'update opportunities', 'import opportunities',
            'assign opportunities', 'qualify opportunities', 'make go no go decision',
            'view capture plans', 'manage capture plans',
            'view proposals', 'view all proposals', 'create proposals', 'update proposals',
            'view crm', 'manage agencies', 'manage companies', 'manage contacts', 'manage activities',
            'view follow ups', 'manage follow ups', 'send follow up emails',
            'view own commissions', 'view all commissions',
            'view dashboards', 'export reports',
            'use ai assistant', 'run document extraction',
        ]);

        // Proposal Manager
        $pm = Role::firstOrCreate(['name' => 'Proposal Manager']);
        $pm->syncPermissions([
            'view opportunities', 'view capture plans',
            'view proposals', 'view all proposals', 'create proposals', 'update proposals',
            'submit proposals', 'approve proposals', 'manage proposal files', 'view private proposal details',
            'view crm', 'view follow ups', 'manage follow ups',
            'view own commissions',
            'view dashboards',
            'use ai assistant', 'run document extraction', 'review ai extraction',
        ]);

        // Proposal Writer
        $pw = Role::firstOrCreate(['name' => 'Proposal Writer']);
        $pw->syncPermissions([
            'view opportunities',
            'view proposals', 'create proposals', 'update proposals', 'manage proposal files',
            'view crm',
            'view follow ups',
            'view own commissions',
            'view dashboards',
            'use ai assistant',
        ]);

        // Capture Manager
        $cm = Role::firstOrCreate(['name' => 'Capture Manager']);
        $cm->syncPermissions([
            'view opportunities', 'update opportunities', 'qualify opportunities', 'make go no go decision',
            'view capture plans', 'manage capture plans', 'manage capture risks', 'manage capture decisions',
            'view proposals',
            'view crm',
            'view follow ups', 'manage follow ups',
            'view own commissions',
            'view dashboards',
            'use ai assistant',
        ]);

        // Sales Representative
        $sales = Role::firstOrCreate(['name' => 'Sales Representative']);
        $sales->syncPermissions([
            'view opportunities', 'create opportunities',
            'view capture plans',
            'view proposals', 'create proposals', 'update proposals',
            'view crm', 'manage contacts', 'manage activities',
            'view follow ups', 'manage follow ups', 'send follow up emails',
            'view own commissions',
            'view dashboards',
            'use ai assistant',
        ]);

        // Finance
        $finance = Role::firstOrCreate(['name' => 'Finance']);
        $finance->syncPermissions([
            'view proposals', 'view all proposals',
            'view own commissions', 'view all commissions', 'approve commissions', 'export commissions',
            'view dashboards', 'view executive dashboard', 'export reports',
        ]);

        // Read Only
        $readOnly = Role::firstOrCreate(['name' => 'Read Only']);
        $readOnly->syncPermissions([
            'view opportunities', 'view capture plans', 'view proposals',
            'view crm', 'view follow ups', 'view dashboards',
        ]);
    }
}
