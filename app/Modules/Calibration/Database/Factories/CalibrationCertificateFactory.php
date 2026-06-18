<?php

namespace App\Modules\Calibration\Database\Factories;

use App\Models\Organization;
use App\Models\User;
use App\Modules\Calibration\Enums\CalibrationResult;
use App\Modules\Calibration\Models\CalibrationCertificate;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CalibrationCertificateFactory extends Factory
{
    protected $model = CalibrationCertificate::class;

    public function definition(): array
    {
        return [
            'ulid' => (string) Str::ulid(),
            'organization_id' => Organization::factory(),
            'created_by' => User::factory(),
            'certificate_number' => strtoupper($this->faker->unique()->bothify('CAL-2026-####')),
            'result' => CalibrationResult::Pass->value,
            'nist_traceable' => true,
            'method' => 'Comparison to NIST-traceable reference',
            'standard_used' => $this->faker->randomElement(['Reference accelerometer #A-12', 'Shaker table ST-3', 'GPS clock standard']),
            'calibrated_at' => now()->toDateString(),
            'due_at' => now()->addYear()->toDateString(),
            'interval_months' => 12,
        ];
    }

    public function overdue(): static
    {
        return $this->state(fn () => ['calibrated_at' => now()->subMonths(14)->toDateString(), 'due_at' => now()->subMonths(2)->toDateString()]);
    }
}
