<?php

namespace Tests\Unit\Services;

use App\Services\Commissions\CommissionCalculationService;
use PHPUnit\Framework\TestCase;

class CommissionCalculationTest extends TestCase
{
    private CommissionCalculationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CommissionCalculationService();
    }

    public function test_percentage_calculation(): void
    {
        $result = $this->service->computeCommission('percentage', 1_000_000, 2.5, null, null);
        $this->assertEquals(25_000, $result);
    }

    public function test_percentage_with_fractional_rate(): void
    {
        $result = $this->service->computeCommission('percentage', 100_000, 1.5, null, null);
        $this->assertEquals(1_500, $result);
    }

    public function test_fixed_amount_calculation(): void
    {
        $result = $this->service->computeCommission('fixed', 999_999, null, 5_000, null);
        $this->assertEquals(5_000, $result);
    }

    public function test_tiered_single_bracket(): void
    {
        $tiers = [
            ['min' => 0, 'max' => null, 'rate' => 3.0],
        ];
        $result = $this->service->computeCommission('tiered', 500_000, null, null, $tiers);
        $this->assertEquals(15_000, $result);
    }

    public function test_tiered_two_brackets(): void
    {
        $tiers = [
            ['min' => 0, 'max' => 500_000, 'rate' => 3.0],
            ['min' => 500_000, 'max' => null, 'rate' => 2.5],
        ];
        // 500k at 3% = 15000, 250k at 2.5% = 6250 = 21250
        $result = $this->service->computeCommission('tiered', 750_000, null, null, $tiers);
        $this->assertEquals(21_250, $result);
    }

    public function test_tiered_three_brackets(): void
    {
        $tiers = [
            ['min' => 0, 'max' => 500_000, 'rate' => 3.0],
            ['min' => 500_000, 'max' => 1_000_000, 'rate' => 2.5],
            ['min' => 1_000_000, 'max' => null, 'rate' => 2.0],
        ];
        // 500k@3%=15000, 500k@2.5%=12500, 500k@2.0%=10000 = 37500
        $result = $this->service->computeCommission('tiered', 1_500_000, null, null, $tiers);
        $this->assertEquals(37_500, $result);
    }

    public function test_zero_base_amount_returns_zero(): void
    {
        $result = $this->service->computeCommission('percentage', 0, 5.0, null, null);
        $this->assertEquals(0, $result);
    }
}
