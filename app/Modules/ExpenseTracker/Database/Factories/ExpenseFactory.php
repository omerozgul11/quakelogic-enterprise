<?php

namespace App\Modules\ExpenseTracker\Database\Factories;

use App\Models\Organization;
use App\Models\User;
use App\Modules\ExpenseTracker\Enums\ExpenseStatus;
use App\Modules\ExpenseTracker\Enums\PaymentMethod;
use App\Modules\ExpenseTracker\Models\Expense;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ExpenseFactory extends Factory
{
    protected $model = Expense::class;

    public function definition(): array
    {
        return [
            'ulid' => (string) Str::ulid(),
            'organization_id' => Organization::factory(),
            'created_by' => User::factory(),
            'owner_id' => User::factory(),
            'number' => 'EXP-'.now()->year.'-'.$this->faker->unique()->numberBetween(1, 99999),
            'vendor' => $this->faker->company(),
            'description' => $this->faker->sentence(4),
            'amount' => $this->faker->randomFloat(2, 10, 2500),
            'currency' => 'USD',
            'payment_method' => PaymentMethod::Card->value,
            'status' => ExpenseStatus::Draft->value,
            'is_billable' => false,
            'expense_date' => $this->faker->dateTimeBetween('-2 months', 'now')->format('Y-m-d'),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => ExpenseStatus::Draft->value]);
    }

    public function submitted(): static
    {
        return $this->state(fn () => [
            'status' => ExpenseStatus::Submitted->value,
            'submitted_at' => now(),
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => ExpenseStatus::Approved->value,
            'submitted_at' => now()->subDay(),
            'approved_at' => now(),
            'approved_by' => User::factory(),
        ]);
    }
}
