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
        $commissionAmount = $this->computeCommission(
            $rule->type->value,
            $baseAmount,
            (float) $rule->rate,
            (float) $rule->fixed_amount,
            $rule->tier_config ?? [],
        );
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
     * Compute a commission amount from primitive inputs. Pure (no DB), unit-testable.
     *
     * @param  string  $type  One of: fixed, fixed_amount, percentage, tiered.
     * @param  array<int, array<string, float|int|null>>|null  $tiers  Bracket definitions for tiered.
     */
    public function computeCommission(
        string $type,
        float $baseAmount,
        ?float $rate = null,
        ?float $fixedAmount = null,
        ?array $tiers = null,
    ): float {
        return match ($type) {
            'fixed', 'fixed_amount' => round((float) ($fixedAmount ?? 0), 2),
            'percentage' => round($baseAmount * ((float) ($rate ?? 0) / 100), 2),
            'tiered' => $this->computeTiered($baseAmount, $tiers ?? []),
            default => 0.0,
        };
    }

    /**
     * Compute tiered commission using bracket arithmetic.
     *
     * Each tier defines an upper bound via either `max` (min/max format) or
     * `up_to` (cumulative-threshold format); a null upper bound means "and above".
     * The portion of the base amount falling within each bracket is taxed at the
     * bracket's `rate` (a percentage).
     *
     * @param  array<int, array<string, float|int|null>>  $tiers
     */
    public function computeTiered(float $amount, array $tiers): float
    {
        $total = 0.0;
        $prevThreshold = 0.0;

        foreach ($tiers as $tier) {
            $upper = $tier['max'] ?? $tier['up_to'] ?? null;
            $rate = ((float) ($tier['rate'] ?? 0)) / 100;
            $lower = $prevThreshold;

            if ($upper === null) {
                $total += max(0.0, $amount - $lower) * $rate;
                break;
            }

            $bracketTop = min($amount, (float) $upper);
            $total += max(0.0, $bracketTop - $lower) * $rate;
            $prevThreshold = (float) $upper;

            if ($amount <= (float) $upper) {
                break;
            }
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
