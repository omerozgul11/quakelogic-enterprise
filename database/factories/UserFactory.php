<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'ulid' => (string) Str::ulid(),
            'organization_id' => Organization::factory(),
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'title' => $this->faker->jobTitle(),
            'avatar_path' => null,
            'email_verified_at' => now(),
            'password' => Hash::make('password123!'),
            'is_active' => true,
            'remember_token' => Str::random(10),
        ];
    }
}
