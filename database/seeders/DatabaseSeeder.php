<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Structural seeders only — these set up the organization, RBAC, and the
        // single admin account. Demo/sample business data is intentionally NOT
        // seeded so the app starts clean for real use.
        $this->call([
            OrganizationSeeder::class,
            RolesPermissionsSeeder::class,
            UserSeeder::class,
            // Starter proposal template library (Phase 7) — useful default content,
            // not demo business data, so it's safe to seed on a clean install.
            ProposalTemplateSeeder::class,
        ]);

        // Demo data seeders (disabled). Re-enable to repopulate sample records:
        // AgencySeeder, CompanySeeder, ContactSeeder, OpportunitySeeder,
        // ProposalSeeder, CommissionSeeder, FollowUpSeeder.
    }
}
