<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CompanyFactory extends Factory
{
    protected $model = Company::class;

    public function definition(): array
    {
        return [
            'ulid' => (string) Str::ulid(),
            'organization_id' => Organization::factory(),
            'created_by' => User::factory(),
            'name' => $this->faker->company(),
            'company_type' => $this->faker->randomElement(['competitor', 'partner', 'vendor', 'subcontractor']),
            'city' => $this->faker->city(),
            'state' => $this->faker->stateAbbr(),
        ];
    }
}
