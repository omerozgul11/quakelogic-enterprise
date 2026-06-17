<?php

namespace App\Console\Commands;

use App\Models\Opportunity;
use App\Services\Opportunities\OpportunityMatchingService;
use Illuminate\Console\Command;

/**
 * Scores active opportunities against every user's expertise profile and records
 * the per-user relevance + primary/secondary recommendation. Runs each morning
 * just before the opportunity digest so users wake up to ranked, recommended
 * opportunities rather than a flat keyword list.
 */
class MatchOpportunitiesCommand extends Command
{
    protected $signature = 'opportunities:match
        {--org= : Limit to one organization}
        {--days=0 : Only score opportunities created/updated within N days (0 = all active)}';

    protected $description = 'Score active opportunities for each user and flag recommended owners.';

    public function handle(OpportunityMatchingService $matching): int
    {
        $days = max(0, (int) $this->option('days'));

        $query = Opportunity::query()
            ->active()
            ->when($this->option('org'), fn ($q) => $q->where('organization_id', $this->option('org')))
            ->when($days > 0, fn ($q) => $q->where('updated_at', '>=', now()->subDays($days)));

        $scored = 0;
        $recommended = 0;

        $query->orderBy('id')->chunkById(100, function ($opportunities) use ($matching, &$scored, &$recommended) {
            foreach ($opportunities as $opportunity) {
                $scores = $matching->scoreOpportunity($opportunity);
                if ($scores !== []) {
                    $scored++;
                    if (max($scores) >= OpportunityMatchingService::RECOMMEND_THRESHOLD) {
                        $recommended++;
                    }
                }
            }
        });

        $this->info("Matched {$scored} opportunit(ies); {$recommended} had a recommendable owner.");

        return self::SUCCESS;
    }
}
