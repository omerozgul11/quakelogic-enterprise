<?php

namespace Database\Factories;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class OrganizationFactory extends Factory
{
    protected $model = Organization::class;

    public function definition(): array
    {
        $name = $this->faker->company();
        return [
            'ulid' => (string) Str::ulid(),
            'name' => $name,
            'slug' => Str::slug($name) . '-' . $this->faker->randomNumber(4),
            'email' => $this->faker->companyEmail(),
            'phone' => $this->faker->phoneNumber(),
            'city' => $this->faker->city(),
            'state' => $this->faker->stateAbbr(),
            'zip' => $this->faker->postcode(),
            'country' => 'US',
            'settings' => [],
        ];
    }
}
