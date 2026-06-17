<?php

namespace Database\Factories;

use App\Models\Opportunity;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class OpportunityFactory extends Factory
{
    protected $model = Opportunity::class;

    public function definition(): array
    {
        $title = $this->faker->sentence(6);
        $solNum = strtoupper($this->faker->bothify('??####-##-R-####'));
        return [
            'ulid' => (string) Str::ulid(),
            'organization_id' => Organization::factory(),
            'created_by' => User::factory(),
            'source' => $this->faker->randomElement(['sam_gov', 'bidprime', 'manual', 'other']),
            'external_id' => $this->faker->uuid(),
            'solicitation_number' => $solNum,
            'title' => $title,
            'description' => $this->faker->paragraphs(2, true),
            'agency_name' => $this->faker->randomElement(['Department of Defense', 'DARPA', 'DHS', 'GSA', 'NASA']),
            'naics_code' => $this->faker->randomElement(['541512', '541330', '541519', '336411']),
            'estimated_value' => $this->faker->randomFloat(2, 100000, 50000000),
            'status' => $this->faker->randomElement(['new', 'qualified', 'pursuing', 'proposal_in_progress']),
            'due_date' => $this->faker->dateTimeBetween('+30 days', '+180 days')->format('Y-m-d'),
            'posted_date' => $this->faker->dateTimeBetween('-60 days', 'now')->format('Y-m-d'),
            'canonical_hash' => hash('sha256', $solNum . $title),
        ];
    }
}
