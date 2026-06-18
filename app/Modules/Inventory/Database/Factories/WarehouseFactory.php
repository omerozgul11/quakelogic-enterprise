<?php

namespace App\Modules\Inventory\Database\Factories;

use App\Models\Organization;
use App\Models\User;
use App\Modules\Inventory\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class WarehouseFactory extends Factory
{
    protected $model = Warehouse::class;

    public function definition(): array
    {
        return [
            'ulid' => (string) Str::ulid(),
            'organization_id' => Organization::factory(),
            'created_by' => User::factory(),
            'code' => strtoupper($this->faker->unique()->bothify('WH-##')),
            'name' => $this->faker->randomElement(['Main Warehouse', 'Production Floor', 'Field Depot', 'Transit Hub']),
            'type' => 'main',
            'city' => $this->faker->city(),
            'state' => $this->faker->stateAbbr(),
            'is_active' => true,
        ];
    }

    public function default(): static
    {
        return $this->state(fn () => ['is_default' => true]);
    }
}
