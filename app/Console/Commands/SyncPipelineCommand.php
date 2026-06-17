<?php

namespace App\Console\Commands;

use App\Models\Organization;
use App\Models\User;
use App\Services\BidSources\MarketAwardsService;
use App\Services\BidSources\OpportunityPipelineService;
use Illuminate\Console\Command;

class SyncPipelineCommand extends Command
{
    protected $signature = 'pipeline:sync {--force : Ignore the sync throttle window}';

    protected $description = 'Refresh the opportunity pipeline in the background: purge expired opportunities and pull fresh + keyword-matched ones from SAM.gov';

    public function handle(OpportunityPipelineService $pipeline, MarketAwardsService $marketAwards): int
    {
        foreach (Organization::query()->get(['id', 'name']) as $org) {
            $purged = $pipeline->purgeExpired($org->id);
            if ($purged > 0) {
                $this->info("[{$org->name}] purged {$purged} expired opportunities");
            }

            if (!$this->option('force') && !$pipeline->shouldSync($org->id)) {
                continue;
            }

            $user = User::where('organization_id', $org->id)->where('is_active', true)->first();
            if (!$user) {
                continue;
            }

            $stats = $pipeline->syncSamGov($user);
            $feed = $marketAwards->refreshShared($org->id);

            // Personal market-pricing feeds for users who saved their own keywords.
            $personalFeeds = 0;
            User::where('organization_id', $org->id)
                ->where('is_active', true)
                ->whereNotNull('market_keywords')
                ->whereRaw("JSON_LENGTH(market_keywords) > 0")
                ->limit(10)
                ->get()
                ->each(function (User $u) use ($marketAwards, &$personalFeeds) {
                    $marketAwards->refresh($u);
                    $personalFeeds++;
                });

            $this->info(sprintf(
                '[%s] imported=%d updated=%d errors=%d purged=%d awards_feed=%d personal_feeds=%d',
                $org->name,
                $stats['imported'] ?? 0,
                $stats['updated'] ?? 0,
                $stats['errors'] ?? 0,
                $stats['purged'] ?? 0,
                count($feed['awards']),
                $personalFeeds,
            ));
        }

        return self::SUCCESS;
    }
}
