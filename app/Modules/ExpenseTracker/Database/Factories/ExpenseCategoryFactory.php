<?php

namespace App\Modules\ExpenseTracker\Database\Factories;

use App\Models\Organization;
use App\Models\User;
use App\Modules\ExpenseTracker\Models\ExpenseCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ExpenseCategoryFactory extends Factory
{
    protected $model = ExpenseCategory::class;

    public function definition(): array
    {
        return [
            'ulid' => (string) Str::ulid(),
            'organization_id' => Organization::factory(),
            'created_by' => User::factory(),
            'name' => $this->faker->unique()->randomElement([
                'Travel', 'Meals & Entertainment', 'Software & SaaS', 'Office Supplies',
                'Marketing', 'Professional Services', 'Equipment', 'Utilities', 'Shipping',
            ]),
            'color' => $this->faker->randomElement(['blue', 'green', 'indigo', 'amber', 'purple', 'teal']),
            'budget_amount' => null,
            'budget_period' => 'monthly',
            'currency' => 'USD',
            'is_active' => true,
        ];
    }

    public function withBudget(float $amount, string $period = 'monthly'): static
    {
        return $this->state(fn () => ['budget_amount' => $amount, 'budget_period' => $period]);
    }
}
