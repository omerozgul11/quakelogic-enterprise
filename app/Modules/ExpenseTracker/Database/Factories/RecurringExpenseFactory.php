<?php

namespace App\Modules\ExpenseTracker\Database\Factories;

use App\Models\Organization;
use App\Models\User;
use App\Modules\ExpenseTracker\Enums\PaymentMethod;
use App\Modules\ExpenseTracker\Enums\RecurringFrequency;
use App\Modules\ExpenseTracker\Models\RecurringExpense;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class RecurringExpenseFactory extends Factory
{
    protected $model = RecurringExpense::class;

    public function definition(): array
    {
        return [
            'ulid' => (string) Str::ulid(),
            'organization_id' => Organization::factory(),
            'created_by' => User::factory(),
            'owner_id' => User::factory(),
            'name' => $this->faker->randomElement(['AWS', 'Microsoft 365', 'Adobe CC', 'Office rent', 'Liability insurance']),
            'vendor' => $this->faker->company(),
            'amount' => $this->faker->randomFloat(2, 20, 5000),
            'currency' => 'USD',
            'payment_method' => PaymentMethod::Card->value,
            'is_billable' => false,
            'frequency' => RecurringFrequency::Monthly->value,
            'interval_count' => 1,
            'start_date' => now()->startOfMonth()->toDateString(),
            'end_date' => null,
            'next_run_date' => now()->startOfMonth()->toDateString(),
            'auto_approve' => false,
            'is_active' => true,
        ];
    }

    /** Due now: next_run_date in the past so the generator picks it up. */
    public function due(): static
    {
        return $this->state(fn () => ['next_run_date' => now()->subDay()->toDateString()]);
    }

    public function autoApprove(): static
    {
        return $this->state(fn () => ['auto_approve' => true]);
    }
}
