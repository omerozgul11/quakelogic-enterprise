<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            OrganizationSeeder::class,
            RolesPermissionsSeeder::class,
            UserSeeder::class,
            AgencySeeder::class,
            CompanySeeder::class,
            ContactSeeder::class,
            OpportunitySeeder::class,
            ProposalSeeder::class,
            CapturePlanSeeder::class,
            CommissionSeeder::class,
            FollowUpSeeder::class,
        ]);
    }
}
