<?php

namespace Database\Seeders;

use App\Models\Commission;
use App\Models\CommissionRule;
use App\Models\Organization;
use App\Models\ProposalSubmission;
use App\Models\User;
use Illuminate\Database\Seeder;

class CommissionSeeder extends Seeder
{
    public function run(): void
    {
        $org = Organization::where('slug', 'quakelogic')->firstOrFail();
        $bdm = User::where('email', 'bdm@quakelogic.net')->first();
        $sales = User::where('email', 'sales@quakelogic.net')->first();
        $finance = User::where('email', 'finance@quakelogic.net')->first();

        // Create commission rules
        $percentageRule = CommissionRule::firstOrCreate(
            ['organization_id' => $org->id, 'name' => 'Standard BD Commission'],
            [
                'organization_id' => $org->id,
                'type' => 'percentage',
                'rate' => 2.5,
                'base_on' => 'award_value',
                'effective_from' => now()->startOfYear()->toDateString(),
                'is_active' => true,
                'notes' => 'Standard 2.5% commission on awarded value for BD team.',
            ]
        );

        $tieredRule = CommissionRule::firstOrCreate(
            ['organization_id' => $org->id, 'name' => 'Tiered Sales Commission'],
            [
                'organization_id' => $org->id,
                'type' => 'tiered',
                'base_on' => 'award_value',
                'tier_config' => [
                    ['up_to' => 1000000, 'rate' => 3.0],
                    ['up_to' => 5000000, 'rate' => 2.5],
                    ['up_to' => null, 'rate' => 2.0],
                ],
                'effective_from' => now()->startOfYear()->toDateString(),
                'is_active' => true,
                'notes' => 'Tiered commission: 3% on first $1M, 2.5% up to $5M, 2% above.',
            ]
        );

        // Create commissions for awarded proposals
        $awardedProposals = ProposalSubmission::where('organization_id', $org->id)
            ->where('status', 'awarded')
            ->get();

        foreach ($awardedProposals as $proposal) {
            // BD commission
            if ($bdm && !Commission::where('user_id', $bdm->id)->where('proposal_submission_id', $proposal->id)->exists()) {
                $awardValue = (float) ($proposal->award_value ?? $proposal->proposal_value ?? 0);
                Commission::create([
                    'organization_id' => $org->id,
                    'user_id' => $bdm->id,
                    'proposal_submission_id' => $proposal->id,
                    'commission_rule_id' => $percentageRule->id,
                    'calculated_by' => $finance?->id ?? $bdm->id,
                    'approved_by' => $finance?->id,
                    'type' => 'percentage',
                    'base_amount' => $awardValue,
                    'rate' => 2.5,
                    'commission_amount' => round($awardValue * 0.025, 2),
                    'status' => 'approved',
                    'period_month' => $proposal->award_date?->format('Y-m') ?? now()->format('Y-m'),
                    'approved_at' => now()->subDays(5),
                ]);
            }

            // Sales commission
            if ($sales && !Commission::where('user_id', $sales->id)->where('proposal_submission_id', $proposal->id)->exists()) {
                $awardValue = (float) ($proposal->award_value ?? $proposal->proposal_value ?? 0);
                Commission::create([
                    'organization_id' => $org->id,
                    'user_id' => $sales->id,
                    'proposal_submission_id' => $proposal->id,
                    'commission_rule_id' => $tieredRule->id,
                    'calculated_by' => $finance?->id ?? $bdm->id,
                    'type' => 'tiered',
                    'base_amount' => $awardValue,
                    'commission_amount' => $this->calculateTiered($awardValue),
                    'status' => 'calculated',
                    'period_month' => $proposal->award_date?->format('Y-m') ?? now()->format('Y-m'),
                ]);
            }
        }
    }

    private function calculateTiered(float $amount): float
    {
        $total = 0.0;
        $tiers = [['up_to' => 1000000, 'rate' => 3.0], ['up_to' => 5000000, 'rate' => 2.5], ['up_to' => null, 'rate' => 2.0]];
        $remaining = $amount;
        $prev = 0.0;

        foreach ($tiers as $tier) {
            $upTo = $tier['up_to'];
            $rate = $tier['rate'] / 100;
            $tierMax = $upTo ? ($upTo - $prev) : $remaining;
            $inTier = min($remaining, $tierMax);
            $total += $inTier * $rate;
            $remaining -= $inTier;
            $prev = $upTo ?? 0;
            if ($remaining <= 0) break;
        }

        return round($total, 2);
    }
}
