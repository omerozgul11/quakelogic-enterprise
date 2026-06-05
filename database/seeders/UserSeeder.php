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

        $users = [
            [
                'name' => 'Admin User',
                'email' => 'admin@quakelogic.net',
                'title' => 'System Administrator',
                'role' => 'Super Admin',
                'password' => 'password123!',
            ],
            [
                'name' => 'Sarah Johnson',
                'email' => 'ceo@quakelogic.net',
                'title' => 'Chief Executive Officer',
                'role' => 'CEO',
                'password' => 'password123!',
            ],
            [
                'name' => 'Marcus Chen',
                'email' => 'bdm@quakelogic.net',
                'title' => 'Business Development Manager',
                'role' => 'Business Development Manager',
                'password' => 'password123!',
            ],
            [
                'name' => 'Emily Rodriguez',
                'email' => 'pm@quakelogic.net',
                'title' => 'Proposal Manager',
                'role' => 'Proposal Manager',
                'password' => 'password123!',
            ],
            [
                'name' => 'James Wilson',
                'email' => 'writer@quakelogic.net',
                'title' => 'Senior Proposal Writer',
                'role' => 'Proposal Writer',
                'password' => 'password123!',
            ],
            [
                'name' => 'Lisa Thompson',
                'email' => 'capture@quakelogic.net',
                'title' => 'Capture Manager',
                'role' => 'Capture Manager',
                'password' => 'password123!',
            ],
            [
                'name' => 'David Park',
                'email' => 'sales@quakelogic.net',
                'title' => 'Sales Representative',
                'role' => 'Sales Representative',
                'password' => 'password123!',
            ],
            [
                'name' => 'Jennifer Lee',
                'email' => 'finance@quakelogic.net',
                'title' => 'Finance Director',
                'role' => 'Finance',
                'password' => 'password123!',
            ],
            [
                'name' => 'Tom Bradley',
                'email' => 'readonly@quakelogic.net',
                'title' => 'Analyst',
                'role' => 'Read Only',
                'password' => 'password123!',
            ],
        ];

        foreach ($users as $userData) {
            $role = $userData['role'];
            unset($userData['role']);

            $user = User::firstOrCreate(['email' => $userData['email']], [
                ...$userData,
                'organization_id' => $org->id,
                'password' => Hash::make($userData['password']),
                'email_verified_at' => now(),
                'is_active' => true,
            ]);

            $user->syncRoles([$role]);
        }
    }
}
