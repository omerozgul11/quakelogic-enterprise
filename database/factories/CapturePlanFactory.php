<?php

namespace Database\Factories;

use App\Models\CapturePlan;
use App\Models\Opportunity;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CapturePlanFactory extends Factory
{
    protected $model = CapturePlan::class;

    public function definition(): array
    {
        return [
            'ulid' => (string) Str::ulid(),
            'organization_id' => Organization::factory(),
            'opportunity_id' => Opportunity::factory(),
            'owner_user_id' => User::factory(),
            'stage' => 'discovery',
            'win_probability' => $this->faker->numberBetween(5, 90),
            'go_no_go_decision' => null,
            'notes' => $this->faker->optional()->sentence(),
        ];
    }
}
