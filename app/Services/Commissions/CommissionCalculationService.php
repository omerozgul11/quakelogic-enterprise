<?php

namespace App\Services\Commissions;

use App\Enums\CommissionType;
use App\Models\Commission;
use App\Models\CommissionRule;
use App\Models\ProposalSubmission;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CommissionCalculationService
{
    /**
     * Calculate commission for a proposal submission and user.
     */
    public function calculate(ProposalSubmission $proposal, User $user, ?CommissionRule $rule = null): Commission
    {
        $rule ??= $this->findApplicableRule($user, $proposal);

        if (!$rule) {
            throw new \RuntimeException("No commission rule found for user {$user->id} on proposal {$proposal->id}");
        }

        $baseAmount = $this->getBaseAmount($proposal, $rule);
        $commissionAmount = $this->computeCommission($baseAmount, $rule);
        $periodMonth = $proposal->award_date?->format('Y-m') ?? now()->format('Y-m');

        return DB::transaction(function () use ($proposal, $user, $rule, $baseAmount, $commissionAmount, $periodMonth) {
            return Commission::create([
                'organization_id' => $proposal->organization_id,
                'user_id' => $user->id,
                'proposal_submission_id' => $proposal->id,
                'commission_rule_id' => $rule->id,
                'type' => $rule->type->value,
                'base_amount' => $baseAmount,
                'rate' => $rule->rate,
                'commission_amount' => $commissionAmount,
                'status' => 'calculated',
                'period_month' => $periodMonth,
            ]);
        });
    }

    /**
     * Compute commission amount from base and rule.
     */
    public function computeCommission(float $baseAmount, CommissionRule $rule): float
    {
        return match ($rule->type) {
            CommissionType::FixedAmount => (float) $rule->fixed_amount,
            CommissionType::Percentage => round($baseAmount * ((float) $rule->rate / 100), 2),
            CommissionType::Tiered => $this->computeTiered($baseAmount, $rule->tier_config ?? []),
        };
    }

    /**
     * Compute tiered commission.
     * tier_config format: [['up_to' => 100000, 'rate' => 5], ['up_to' => 500000, 'rate' => 3], ['up_to' => null, 'rate' => 2]]
     */
    public function computeTiered(float $amount, array $tiers): float
    {
        $total = 0.0;
        $remaining = $amount;
        $prevThreshold = 0.0;

        foreach ($tiers as $tier) {
            $upTo = $tier['up_to'] ?? null;
            $rate = ($tier['rate'] ?? 0) / 100;

            if ($upTo === null) {
                // Final tier: apply to all remaining
                $total += $remaining * $rate;
                break;
            }

            $tierMax = (float) $upTo - $prevThreshold;
            $inTier = min($remaining, $tierMax);
            $total += $inTier * $rate;
            $remaining -= $inTier;
            $prevThreshold = (float) $upTo;

            if ($remaining <= 0) break;
        }

        return round($total, 2);
    }

    private function getBaseAmount(ProposalSubmission $proposal, CommissionRule $rule): float
    {
        return match ($rule->base_on) {
            'proposal_value' => (float) ($proposal->proposal_value ?? 0),
            'award_value' => (float) ($proposal->award_value ?? $proposal->proposal_value ?? 0),
            'margin' => (float) ($proposal->award_value ?? 0) * ((float) ($proposal->estimated_margin ?? 0) / 100),
            default => (float) ($proposal->award_value ?? $proposal->proposal_value ?? 0),
        };
    }

    private function findApplicableRule(User $user, ProposalSubmission $proposal): ?CommissionRule
    {
        return CommissionRule::where('organization_id', $proposal->organization_id)
            ->where('is_active', true)
            ->where('effective_from', '<=', now()->toDateString())
            ->where(fn($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', now()->toDateString()))
            ->where(fn($q) => $q->where('user_id', $user->id)->orWhereNull('user_id'))
            ->orderBy('user_id', 'desc')
            ->first();
    }
}
