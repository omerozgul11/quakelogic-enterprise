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
// Daily: morning opportunity digest per user (keyword matches) into their Inbox,
// and deadline reminders for proposals due within the next 5 days.
Schedule::command('inbox:opportunity-digest')->dailyAt('07:00')->withoutOverlapping();
Schedule::command('proposals:deadline-reminders')->dailyAt('07:30')->withoutOverlapping();
// Daily: delete in-app notifications older than 30 days so the bell list and
// the notifications table don't grow without bound.
Schedule::command('notifications:prune')->dailyAt('03:00')->withoutOverlapping();

// Background pipeline refresh: runs every 5 minutes so opportunities stay
// fresh without anyone having the page open. The expired purge runs each
// time; the SAM.gov pull respects pipeline.sync_throttle_minutes to stay
// within SAM's API rate limits.
Schedule::command('pipeline:sync')->everyFiveMinutes()->withoutOverlapping();

// Shipments: refresh active mailings from UPS every 30 minutes (delivered/
// returned are skipped by the command's active() scope).
Schedule::command('mailings:poll')->everyThirtyMinutes()->withoutOverlapping();

// Auto-ingest account shipments from UPS Quantum View (only when enabled, so the
// dev simulator never floods the list). New shipments created on the UPS account
// appear automatically; mailings:poll then enriches each with its full timeline.
if (config('services.ups.quantum_view.enabled')) {
    Schedule::command('mailings:ingest')->hourly()->withoutOverlapping();
}
