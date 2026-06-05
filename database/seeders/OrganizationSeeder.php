<?php

namespace Database\Seeders;

use App\Models\Organization;
use Illuminate\Database\Seeder;

class OrganizationSeeder extends Seeder
{
    public function run(): void
    {
        Organization::firstOrCreate(['slug' => 'quakelogic'], [
            'name' => 'QuakeLogic',
            'legal_name' => 'QuakeLogic Inc.',
            'cage_code' => '9QL01',
            'website' => 'https://quakelogic.net',
            'email' => 'info@quakelogic.net',
            'phone' => '(703) 555-0100',
            'address_line1' => '1234 Innovation Drive',
            'city' => 'McLean',
            'state' => 'VA',
            'zip' => '22102',
            'country' => 'US',
            'timezone' => 'America/New_York',
            'is_active' => true,
        ]);
    }
}
