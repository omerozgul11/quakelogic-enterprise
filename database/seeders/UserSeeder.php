<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $org = Organization::where('slug', 'quakelogic')->firstOrFail();

        // Bootstrap admin + the named team from the Opportunity Assignment spec.
        // Each carries an expertise profile (keywords + product/industry/geo +
        // value band) so the match-scoring and AI assignment engines work out of
        // the box. pipeline_keywords drives the existing "For You" feed + digest.
        $users = [
            [
                'name' => 'Admin User',
                'email' => 'admin@quakelogic.net',
                'title' => 'System Administrator',
                'department' => 'IT',
                'role' => 'Super Admin',
                'password' => 'password123!',
            ],
            [
                'name' => 'Erol',
                'email' => 'erol@quakelogic.net',
                'title' => 'CEO',
                'department' => 'Executive',
                'role' => 'Super Admin',
                'password' => 'password123!',
                'pipeline_keywords' => [
                    'Earthquake', 'Seismic', 'Structural Health Monitoring', 'Shake Table',
                    'Nuclear', 'Monitoring', 'Sensors', 'Infrasound', 'Geotechnical', 'Research',
                ],
                'product_expertise' => [
                    'Seismographs', 'Shake Tables', 'SHM Systems', 'Earthquake Early Warning',
                    'Accelerometers', 'Strong Motion Sensors',
                ],
                'industry_expertise' => ['Nuclear', 'Civil Engineering', 'Research & Academia', 'Government', 'Defense'],
                'geographic_focus' => ['United States', 'International'],
                'min_opportunity_value' => 100000,
                'max_opportunity_value' => null,
            ],
            [
                'name' => 'Gorkem',
                'email' => 'gorkem@quakelogic.net',
                'title' => 'Business Development Manager',
                'department' => 'Business Development',
                'role' => 'Business Development Manager',
                'password' => 'password123!',
                'pipeline_keywords' => [
                    'Educational Equipment', 'Simulator', 'Manufacturing', 'Training',
                    'Laboratory', 'STEM', 'Vocational', 'Workforce',
                ],
                'product_expertise' => ['Simulators', 'Training Equipment', 'Laboratory Equipment', 'CNC / Manufacturing'],
                'industry_expertise' => ['Education', 'Manufacturing', 'Workforce Development'],
                'geographic_focus' => ['United States'],
                'min_opportunity_value' => 25000,
                'max_opportunity_value' => 5000000,
            ],
            [
                'name' => 'Sophia',
                'email' => 'sophia@quakelogic.net',
                'title' => 'Account Manager',
                'department' => 'Customer Success',
                'role' => 'Sales Representative',
                'password' => 'password123!',
                'pipeline_keywords' => [
                    'Renewal', 'Contract', 'Maintenance', 'Support', 'Existing Customer',
                    'Service Agreement', 'Warranty', 'Calibration',
                ],
                'product_expertise' => ['Service Contracts', 'Maintenance Plans', 'Calibration Services'],
                'industry_expertise' => ['Customer Success', 'Account Management', 'Government'],
                'geographic_focus' => ['United States'],
                'min_opportunity_value' => 5000,
                'max_opportunity_value' => 2000000,
            ],
            [
                'name' => 'Omer',
                'email' => 'omer@quakelogic.net',
                'title' => 'Director of Technology',
                'department' => 'Technology',
                'role' => 'Super Admin',
                'password' => 'password123!',
                'pipeline_keywords' => [
                    'Software', 'Cloud', 'AI', 'IT', 'SaaS', 'Data', 'Cybersecurity',
                    'Machine Learning', 'Platform', 'Integration',
                ],
                'product_expertise' => ['Software Platforms', 'Cloud Infrastructure', 'AI / ML', 'Data Systems', 'Cybersecurity'],
                'industry_expertise' => ['Information Technology', 'Technology', 'Defense IT'],
                'geographic_focus' => ['United States', 'Remote'],
                'min_opportunity_value' => 50000,
                'max_opportunity_value' => 10000000,
            ],
        ];

        foreach ($users as $userData) {
            $role = $userData['role'];
            $password = $userData['password'];
            unset($userData['role'], $userData['password']);

            // Identity + password on first creation only (never reset on re-seed).
            $user = User::firstOrCreate(['email' => $userData['email']], [
                'name' => $userData['name'],
                'organization_id' => $org->id,
                'password' => Hash::make($password),
                'email_verified_at' => now(),
                'is_active' => true,
            ]);

            // Apply / refresh the profile fields (idempotent, password untouched).
            $user->fill($userData)->save();
            $user->syncRoles([$role]);
        }
    }
}
