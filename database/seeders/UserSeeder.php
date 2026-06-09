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

        // Single bootstrap admin only. Change the email/password after first login.
        $users = [
            [
                'name' => 'Admin User',
                'email' => 'admin@quakelogic.net',
                'title' => 'System Administrator',
                'role' => 'Super Admin',
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
