<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
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

            // Proposals
            'view proposals', 'view all proposals', 'create proposals', 'update proposals', 'delete proposals',
            'submit proposals', 'approve proposals', 'manage proposal files', 'view private proposal details',

            // CRM
            'view crm', 'manage agencies', 'manage companies', 'manage contacts', 'manage activities',

            // CRM section (/crm) — `access crm` gates the whole section (mirrors
            // `access shipments`) and is granted to every role below.
            'access crm', 'manage leads', 'manage projects', 'manage invoices',

            // Inventory module (/inventory) — `access inventory` gates the
            // section; granted to every role below (view-only for Read Only).
            'access inventory', 'view inventory', 'manage products', 'manage warehouses', 'adjust stock',

            // Follow-ups
            'view follow ups', 'manage follow ups', 'send follow up emails',

            // Commissions
            'view own commissions', 'view all commissions', 'manage commission rules',
            'approve commissions', 'export commissions',

            // Reports
            'view dashboards', 'view executive dashboard', 'export reports',

            // AI
            'use ai assistant', 'run document extraction', 'review ai extraction', 'manage ai settings',

            // Shipments (sibling app — UPS tracking for mailed proposals).
            // Gating the whole Shipments app. Super Admin gets it via the
            // Permission::all() sync below; grant to additional roles here when
            // they should be able to reach Shipments (no Shipments deploy needed).
            'access shipments', 'manage mailings',

            // Contracts & Financials (Phase 5)
            'view contracts', 'manage contracts',

            // Compliance & Template Library (Phase 7)
            'view compliance', 'manage compliance', 'view templates', 'manage templates',
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
            'view proposals', 'view all proposals', 'create proposals', 'update proposals',
            'submit proposals', 'approve proposals', 'manage proposal files', 'view private proposal details',
            'view crm', 'manage agencies', 'manage companies', 'manage contacts', 'manage activities',
            'access crm', 'manage leads', 'manage projects', 'manage invoices',
            'view follow ups', 'manage follow ups', 'send follow up emails',
            'view own commissions', 'view all commissions', 'manage commission rules', 'approve commissions', 'export commissions',
            'view dashboards', 'view executive dashboard', 'export reports',
            'use ai assistant', 'run document extraction', 'review ai extraction',
            'view contracts', 'manage contracts',
            'view templates', 'manage templates',
        ]);

        // Business Development Manager
        $bdm = Role::firstOrCreate(['name' => 'Business Development Manager']);
        $bdm->syncPermissions([
            'view opportunities', 'create opportunities', 'update opportunities', 'import opportunities',
            'assign opportunities', 'qualify opportunities', 'make go no go decision',
            'view proposals', 'view all proposals', 'create proposals', 'update proposals',
            'view crm', 'manage agencies', 'manage companies', 'manage contacts', 'manage activities',
            'access crm', 'manage leads', 'manage projects', 'manage invoices',
            'view follow ups', 'manage follow ups', 'send follow up emails',
            'view own commissions', 'view all commissions',
            'view dashboards', 'export reports',
            'use ai assistant', 'run document extraction',
            'view contracts', 'manage contracts',
            'view templates', 'manage templates',
        ]);

        // Proposal Manager
        $pm = Role::firstOrCreate(['name' => 'Proposal Manager']);
        $pm->syncPermissions([
            'view opportunities',
            'view proposals', 'view all proposals', 'create proposals', 'update proposals',
            'submit proposals', 'approve proposals', 'manage proposal files', 'view private proposal details',
            'view crm', 'view follow ups', 'manage follow ups',
            'access crm', 'manage leads', 'manage projects', 'manage invoices',
            'view own commissions',
            'view dashboards',
            'use ai assistant', 'run document extraction', 'review ai extraction',
            'view contracts', 'manage contracts',
            'view templates', 'manage templates',
        ]);

        // Proposal Writer
        $pw = Role::firstOrCreate(['name' => 'Proposal Writer']);
        $pw->syncPermissions([
            'view opportunities',
            'view proposals', 'create proposals', 'update proposals', 'manage proposal files',
            'view crm', 'access crm',
            'view follow ups',
            'view own commissions',
            'view dashboards',
            'use ai assistant',
            'view contracts',
            'view templates', 'manage templates',
        ]);

        // Sales Representative
        $sales = Role::firstOrCreate(['name' => 'Sales Representative']);
        $sales->syncPermissions([
            'view opportunities', 'create opportunities',
            'view proposals', 'create proposals', 'update proposals',
            'view crm', 'manage contacts', 'manage activities',
            'access crm', 'manage leads', 'manage projects',
            'view follow ups', 'manage follow ups', 'send follow up emails',
            'view own commissions',
            'view dashboards',
            'use ai assistant',
            'view contracts',
            'view templates',
        ]);

        // Finance
        $finance = Role::firstOrCreate(['name' => 'Finance']);
        $finance->syncPermissions([
            'view own commissions', 'view all commissions', 'approve commissions', 'export commissions',
            'view dashboards', 'view executive dashboard', 'export reports',
            'view contracts', 'manage contracts',
            'access crm', 'view crm', 'manage invoices',
        ]);

        // Read Only
        $readOnly = Role::firstOrCreate(['name' => 'Read Only']);
        $readOnly->syncPermissions([
            'view opportunities', 'view proposals',
            'view crm', 'access crm', 'view follow ups', 'view dashboards',
            'view contracts', 'view templates',
        ]);

        // Collaborative proposal editing: every role can view + edit every
        // proposal (the ProposalSubmissionPolicy no longer restricts edit to
        // owner/team). Applied via the pivot so it layers on top of the
        // per-role syncPermissions above without re-listing it in each.
        $proposalEditing = Permission::whereIn('name', [
            'view proposals', 'view all proposals', 'update proposals',
            'view private proposal details', 'manage proposal files',
        ])->pluck('id');
        foreach (Role::pluck('id') as $roleId) {
            foreach ($proposalEditing as $permId) {
                DB::table('role_has_permissions')->updateOrInsert([
                    'permission_id' => $permId,
                    'role_id' => $roleId,
                ]);
            }
        }

        // Inventory section: everyone can reach + view it; everyone except Read
        // Only can manage products/warehouses and move stock. Applied via the
        // pivot so it layers on top of each role's syncPermissions above.
        $inventoryView = Permission::whereIn('name', ['access inventory', 'view inventory'])->pluck('id');
        $inventoryManage = Permission::whereIn('name', ['manage products', 'manage warehouses', 'adjust stock'])->pluck('id');
        foreach (Role::all(['id', 'name']) as $role) {
            $grant = $role->name === 'Read Only' ? $inventoryView : $inventoryView->merge($inventoryManage);
            foreach ($grant as $permId) {
                DB::table('role_has_permissions')->updateOrInsert([
                    'permission_id' => $permId,
                    'role_id' => $role->id,
                ]);
            }
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
