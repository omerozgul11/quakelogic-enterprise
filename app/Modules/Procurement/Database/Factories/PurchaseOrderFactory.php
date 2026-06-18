<?php

namespace App\Modules\Procurement\Database\Factories;

use App\Models\Organization;
use App\Models\User;
use App\Modules\Procurement\Enums\PurchaseOrderStatus;
use App\Modules\Procurement\Models\PurchaseOrder;
use App\Modules\Procurement\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PurchaseOrderFactory extends Factory
{
    protected $model = PurchaseOrder::class;

    public function definition(): array
    {
        return [
            'ulid' => (string) Str::ulid(),
            'organization_id' => Organization::factory(),
            'created_by' => User::factory(),
            'procurement_supplier_id' => Supplier::factory(),
            'number' => 'PO-2026-'.str_pad((string) $this->faker->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'status' => PurchaseOrderStatus::Draft->value,
            'order_date' => now()->toDateString(),
            'currency' => 'USD',
        ];
    }
}
