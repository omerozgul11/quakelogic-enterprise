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
            'capture_manager_id' => User::factory(),
            'created_by' => User::factory(),
            'stage' => 'discovery',
            'probability_of_win' => $this->faker->numberBetween(5, 90),
            'notes' => $this->faker->optional()->sentence(),
        ];
    }
}
