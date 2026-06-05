<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;

class CompanySeeder extends Seeder
{
    public function run(): void
    {
        $org = Organization::where('slug', 'quakelogic')->firstOrFail();
        $user = User::where('organization_id', $org->id)->first();

        $companies = [
            ['name' => 'Lockheed Martin', 'company_type' => 'Competitor', 'industry' => 'Defense', 'cage_code' => 'LM001', 'website' => 'https://www.lockheedmartin.com', 'city' => 'Bethesda', 'state' => 'MD'],
            ['name' => 'Booz Allen Hamilton', 'company_type' => 'Competitor', 'industry' => 'Consulting', 'cage_code' => 'BAH01', 'website' => 'https://www.boozallen.com', 'city' => 'McLean', 'state' => 'VA'],
            ['name' => 'Leidos', 'company_type' => 'Competitor', 'industry' => 'Technology', 'website' => 'https://www.leidos.com', 'city' => 'Reston', 'state' => 'VA'],
            ['name' => 'SAIC', 'company_type' => 'Competitor', 'industry' => 'Technology', 'website' => 'https://www.saic.com', 'city' => 'Reston', 'state' => 'VA'],
            ['name' => 'Peraton', 'company_type' => 'Competitor', 'industry' => 'Technology', 'city' => 'Herndon', 'state' => 'VA'],
            ['name' => 'TechForce Solutions', 'company_type' => 'Partner', 'industry' => 'Technology', 'cage_code' => 'TFS01', 'city' => 'Arlington', 'state' => 'VA'],
            ['name' => 'CyberShield Inc', 'company_type' => 'Partner', 'industry' => 'Cybersecurity', 'cage_code' => 'CSI01', 'city' => 'Tysons', 'state' => 'VA'],
            ['name' => 'DataBridge Analytics', 'company_type' => 'Vendor', 'industry' => 'Data Analytics', 'city' => 'Washington', 'state' => 'DC'],
            ['name' => 'CloudPath Technologies', 'company_type' => 'Vendor', 'industry' => 'Cloud Services', 'city' => 'Rockville', 'state' => 'MD'],
        ];

        foreach ($companies as $data) {
            Company::firstOrCreate(
                ['organization_id' => $org->id, 'name' => $data['name']],
                [...$data, 'organization_id' => $org->id, 'created_by' => $user->id]
            );
        }
    }
}
