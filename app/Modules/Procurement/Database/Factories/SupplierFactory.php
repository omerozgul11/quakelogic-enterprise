<?php

namespace App\Modules\Procurement\Database\Factories;

use App\Models\Organization;
use App\Models\User;
use App\Modules\Procurement\Enums\SupplierStatus;
use App\Modules\Procurement\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class SupplierFactory extends Factory
{
    protected $model = Supplier::class;

    public function definition(): array
    {
        return [
            'ulid' => (string) Str::ulid(),
            'organization_id' => Organization::factory(),
            'created_by' => User::factory(),
            'code' => strtoupper($this->faker->unique()->bothify('SUP-###')),
            'name' => $this->faker->company(),
            'category' => $this->faker->randomElement(['Electronics', 'Machining', 'Castings', 'Logistics', 'Calibration']),
            'status' => SupplierStatus::Active->value,
            'email' => $this->faker->companyEmail(),
            'phone' => $this->faker->phoneNumber(),
            'payment_terms' => $this->faker->randomElement(['Net 30', 'Net 45', 'Net 60', 'Due on receipt']),
            'currency' => 'USD',
            'lead_time_days' => $this->faker->numberBetween(3, 45),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => SupplierStatus::Inactive->value]);
    }
}
