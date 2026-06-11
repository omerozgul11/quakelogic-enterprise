<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\BidSources\MarketAwardsService;
use App\Services\BidSources\OpportunityPipelineService;
use Illuminate\Auth\Events\Login;

/**
 * On every login, keep the opportunity pipeline current:
 *  - Immediately remove past-due opportunities (cheap, runs inline).
 *  - Pull fresh opportunities from SAM.gov after the response is sent
 *    (throttled), so login stays fast and the external call never blocks it.
 */
class RefreshPipelineOnLogin
{
    public function __construct(
        private readonly OpportunityPipelineService $pipeline,
        private readonly MarketAwardsService $marketAwards,
    ) {}

    public function handle(Login $event): void
    {
        $user = $event->user;
        if (!$user instanceof User || !$user->organization_id) {
            return;
        }

        // Always drop expired opportunities right away so the user never lands
        // on a stale pipeline.
        try {
            $this->pipeline->purgeExpired($user->organization_id);
        } catch (\Throwable) {
            // Non-fatal: never block login on pipeline maintenance.
        }

        // Refresh from SAM.gov in the background of this request (after the
        // redirect is flushed), and only when the throttle window has elapsed.
        if ($this->pipeline->shouldSync($user->organization_id)) {
            app()->terminating(function () use ($user) {
                $this->pipeline->syncSamGov($user);
                $this->marketAwards->refreshShared($user->organization_id);
                if (($user->market_keywords ?? []) !== []) {
                    $this->marketAwards->refresh($user);
                }
            });
        }
    }
}
