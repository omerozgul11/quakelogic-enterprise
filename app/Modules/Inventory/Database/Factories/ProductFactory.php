<?php

namespace App\Modules\Inventory\Database\Factories;

use App\Models\Organization;
use App\Models\User;
use App\Modules\Inventory\Enums\ProductType;
use App\Modules\Inventory\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $cost = $this->faker->randomFloat(2, 5, 2000);

        return [
            'ulid' => (string) Str::ulid(),
            'organization_id' => Organization::factory(),
            'created_by' => User::factory(),
            'sku' => strtoupper($this->faker->unique()->bothify('SKU-####-???')),
            'name' => $this->faker->words(3, true),
            'type' => $this->faker->randomElement(ProductType::cases())->value,
            'category' => $this->faker->randomElement(['Sensors', 'Digitizers', 'Accessories', 'Cables', 'Spare Parts']),
            'unit_of_measure' => 'each',
            'unit_cost' => $cost,
            'unit_price' => round($cost * $this->faker->randomFloat(2, 1.2, 2.5), 2),
            'currency' => 'USD',
            'reorder_point' => $this->faker->randomElement([null, 5, 10, 25]),
            'is_active' => true,
        ];
    }

    public function serialized(): static
    {
        return $this->state(fn () => ['is_serialized' => true]);
    }
}
