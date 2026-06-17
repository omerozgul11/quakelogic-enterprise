<?php

namespace App\Services\Proposals;

use App\Models\ProposalSubmission;
use App\Support\Currency;

/**
 * Quick profit-margin estimate for a proposal: the bid (proposal_value) minus
 * the sum of its cost line items gives a potential profit and margin %. This is
 * an estimate only — costs that aren't known until project completion (extra
 * travel, installation overruns, overhead) are whatever the user has entered.
 */
class ProposalMarginService
{
    /**
     * Per-proposal summary in the proposal's own currency.
     *
     * @return array{currency:string,bid:?float,cost:float,profit:?float,margin:?float,has_bid:bool,line_count:int}
     */
    public function summary(ProposalSubmission $proposal): array
    {
        $currency = $proposal->currency ?? Currency::DEFAULT;

        $cost = (float) ($proposal->relationLoaded('costs')
            ? $proposal->costs->sum('amount')
            : $proposal->costs()->sum('amount'));

        $lineCount = $proposal->relationLoaded('costs')
            ? $proposal->costs->count()
            : $proposal->costs()->count();

        $bid = $proposal->proposal_value !== null ? (float) $proposal->proposal_value : 0.0;
        $hasBid = $bid > 0;

        return [
            'currency' => $currency,
            'bid' => $hasBid ? round($bid, 2) : null,
            'cost' => round($cost, 2),
            'profit' => $hasBid ? round($bid - $cost, 2) : null,
            'margin' => $hasBid ? round(($bid - $cost) / $bid * 100, 1) : null,
            'has_bid' => $hasBid,
            'line_count' => $lineCount,
        ];
    }
}
