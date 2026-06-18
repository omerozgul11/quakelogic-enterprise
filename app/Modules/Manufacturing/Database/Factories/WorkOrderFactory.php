<?php

namespace App\Modules\Manufacturing\Database\Factories;

use App\Models\Organization;
use App\Models\User;
use App\Modules\Inventory\Models\Product;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Manufacturing\Enums\WorkOrderStatus;
use App\Modules\Manufacturing\Models\WorkOrder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class WorkOrderFactory extends Factory
{
    protected $model = WorkOrder::class;

    public function definition(): array
    {
        return [
            'ulid' => (string) Str::ulid(),
            'organization_id' => Organization::factory(),
            'created_by' => User::factory(),
            'inventory_product_id' => Product::factory(),
            'inventory_warehouse_id' => Warehouse::factory(),
            'number' => 'WO-2026-'.str_pad((string) $this->faker->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'status' => WorkOrderStatus::Draft->value,
            'quantity_planned' => $this->faker->numberBetween(1, 50),
            'scheduled_date' => now()->toDateString(),
        ];
    }
}
