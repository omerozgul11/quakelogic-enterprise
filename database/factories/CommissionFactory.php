<?php

namespace Database\Factories;

use App\Models\Commission;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CommissionFactory extends Factory
{
    protected $model = Commission::class;

    public function definition(): array
    {
        $baseAmount = $this->faker->randomFloat(2, 100000, 5000000);
        $rate = $this->faker->randomFloat(2, 1, 5);
        $commissionAmount = round($baseAmount * ($rate / 100), 2);
        return [
            'organization_id' => Organization::factory(),
            'user_id' => User::factory(),
            'commission_rule_id' => null,
            'proposal_submission_id' => null,
            'type' => 'percentage',
            'base_amount' => $baseAmount,
            'rate' => $rate,
            'commission_amount' => $commissionAmount,
            'status' => 'pending',
            'period_month' => now()->format('Y-m'),
            'notes' => null,
        ];
    }
}
