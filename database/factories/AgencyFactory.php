<?php

namespace Database\Factories;

use App\Models\Agency;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class AgencyFactory extends Factory
{
    protected $model = Agency::class;

    public function definition(): array
    {
        return [
            'ulid' => (string) Str::ulid(),
            'organization_id' => Organization::factory(),
            'name' => $this->faker->company() . ' Agency',
            'acronym' => strtoupper($this->faker->lexify('???')),
            'agency_type' => $this->faker->randomElement(['federal', 'state', 'local', 'international']),
            'city' => $this->faker->city(),
            'state' => $this->faker->stateAbbr(),
            'country' => 'US',
        ];
    }
}
