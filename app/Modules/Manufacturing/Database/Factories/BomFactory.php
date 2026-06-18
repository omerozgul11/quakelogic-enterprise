<?php

namespace App\Modules\Manufacturing\Database\Factories;

use App\Models\Organization;
use App\Models\User;
use App\Modules\Inventory\Models\Product;
use App\Modules\Manufacturing\Enums\BomStatus;
use App\Modules\Manufacturing\Models\Bom;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class BomFactory extends Factory
{
    protected $model = Bom::class;

    public function definition(): array
    {
        return [
            'ulid' => (string) Str::ulid(),
            'organization_id' => Organization::factory(),
            'created_by' => User::factory(),
            'inventory_product_id' => Product::factory(),
            'name' => $this->faker->words(2, true).' BOM',
            'version' => 'v1',
            'status' => BomStatus::Active->value,
            'output_quantity' => 1,
            'is_default' => true,
        ];
    }
}
