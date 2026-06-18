<?php

namespace App\Modules\AssetManagement\Database\Factories;

use App\Models\Organization;
use App\Models\User;
use App\Modules\AssetManagement\Enums\AssetStatus;
use App\Modules\AssetManagement\Models\Asset;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class AssetFactory extends Factory
{
    protected $model = Asset::class;

    public function definition(): array
    {
        return [
            'ulid' => (string) Str::ulid(),
            'organization_id' => Organization::factory(),
            'created_by' => User::factory(),
            'asset_tag' => strtoupper($this->faker->unique()->bothify('AST-2026-####')),
            'name' => $this->faker->randomElement(['F330 Accelerometer', 'CUBE Digitizer', 'AIR Sensor', 'Shake Table Controller']),
            'serial_number' => strtoupper($this->faker->bothify('SN-#####')),
            'status' => AssetStatus::Active->value,
            'category' => $this->faker->randomElement(['Sensors', 'Digitizers', 'Test Equipment']),
            'condition' => $this->faker->randomElement(['new', 'good', 'fair']),
            'purchase_cost' => $this->faker->randomFloat(2, 500, 5000),
            'currency' => 'USD',
            'purchased_at' => now()->subMonths($this->faker->numberBetween(1, 36))->toDateString(),
        ];
    }

    public function deployed(): static
    {
        return $this->state(fn () => ['status' => AssetStatus::Deployed->value, 'deployed_at' => now()->toDateString()]);
    }
}
