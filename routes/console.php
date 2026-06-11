<?php

use App\Console\Commands\SyncBidSourcesCommand;
use App\Console\Commands\GenerateFollowUpsCommand;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Scheduled jobs
Schedule::command('bids:sync sam-gov')->dailyAt('06:00')->withoutOverlapping();
Schedule::command('bids:sync bidprime')->dailyAt('06:30')->withoutOverlapping();
Schedule::command('follow-ups:generate')->dailyAt('08:00')->withoutOverlapping();
// Monthly status follow-up per open proposal (emails only when a mailbox is connected).
Schedule::command('follow-ups:monthly')->monthlyOn(1, '08:30')->withoutOverlapping();

// Background pipeline refresh: runs every 5 minutes so opportunities stay
// fresh without anyone having the page open. The expired purge runs each
// time; the SAM.gov pull respects pipeline.sync_throttle_minutes to stay
// within SAM's API rate limits.
Schedule::command('pipeline:sync')->everyFiveMinutes()->withoutOverlapping();
