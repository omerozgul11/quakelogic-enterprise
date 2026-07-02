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
// BidPrime via Gmail: only scheduled once live ingest is enabled (otherwise the
// fake inbox would create demo opportunities). Manual: php artisan bidprime:ingest-email
if (config('integrations.bidprime.email.enabled')) {
    Schedule::command('bidprime:ingest-email')->dailyAt('06:15')->withoutOverlapping();
}
// Daily: pull missing solicitation documents for opportunities that synced
// without them (SAM full-text imports / BidPrime leads). Each is resolved to its
// SAM.gov notice and the official record's resourceLinks are merged in. Bounded
// per night and throttled for SAM's rate limits; runs after the source syncs.
// Backfill the whole history by hand: php artisan opportunities:backfill-sam-documents
// Pull solicitation documents for ALL opportunities that still lack them, every
// day. Non-force so it converges: notices confirmed empty are skipped for a week
// (quota goes to unchecked/new ones), and it self-stops when SAM starts throttling,
// resuming the next day until the whole backlog — and each day's new arrivals — is
// covered. No --limit: cache-served rows are skipped instantly, quota is the real bound.
Schedule::command('opportunities:backfill-sam-documents')
    ->dailyAt('07:05')->withoutOverlapping()->runInBackground();
// Generate any recurring vendor bills that have come due.
Schedule::command('procurement:generate-recurring-bills')->dailyAt('05:30')->withoutOverlapping();
Schedule::command('follow-ups:generate')->dailyAt('08:00')->withoutOverlapping();
// Monthly status follow-up per open proposal (emails only when a mailbox is connected).
Schedule::command('follow-ups:monthly')->monthlyOn(1, '08:30')->withoutOverlapping();
// Every minute: refresh the dashboard's exchange-rate cache with near-real-time
// market quotes (free, no-key Yahoo Finance feed; ECB daily + static reference
// are the automatic fallbacks). The dashboard reads the warmed cache instantly,
// so rates track the live market without anyone reloading.
Schedule::command('exchange-rates:refresh')->everyMinute()->withoutOverlapping();
// Daily: score active opportunities against each user's expertise profile and
// flag recommended owners — runs just before the digest so it can rank them.
Schedule::command('opportunities:match')->dailyAt('06:45')->withoutOverlapping();
// Daily: morning opportunity digest per user (keyword matches, ranked by match
// score) into their Inbox, and deadline reminders for proposals due within 5 days.
Schedule::command('inbox:opportunity-digest')->dailyAt('07:00')->withoutOverlapping();
Schedule::command('proposals:deadline-reminders')->dailyAt('07:30')->withoutOverlapping();
// Daily: nudge CRM users about follow-ups due today / overdue (once per day each).
Schedule::command('crm:follow-up-reminders')->dailyAt('07:50')->withoutOverlapping();
// Hourly: opportunity assignment escalation — assigned-but-untouched opportunities
// climb the 24h→48h→72h→96h ladder (owner→manager→admin→reassignment candidate).
Schedule::command('opportunities:escalate')->hourly()->withoutOverlapping();
// Daily: executive opportunity briefing to admins/CEO (counts, at-risk, workload,
// recommended reassignments) into their Inbox.
Schedule::command('executive:briefing')->dailyAt('07:15')->withoutOverlapping();
// Daily: proposal health — escalate stale (no client contact) proposals up the
// owner→manager→admin ladder, and nudge pending-award proposals until decided.
Schedule::command('proposals:health')->dailyAt('07:45')->withoutOverlapping();
// Daily: delete in-app notifications older than 30 days so the bell list and
// the notifications table don't grow without bound.
Schedule::command('notifications:prune')->dailyAt('03:00')->withoutOverlapping();

// Daily: roll any due recurring costs/subscriptions (SaaS, rent, payroll) into
// real expense rows and advance each schedule's next run date. Catch-up safe.
Schedule::command('expenses:generate-recurring')->dailyAt('05:00')->withoutOverlapping();

// Backstop poll for QuickBooks. Real-time sync is event-driven (the Intuit
// webhook for QuickBooks→app, and the ExpenseObserver push for app→QuickBooks);
// this periodic catch-up reconciles anything a webhook missed. No-ops when
// nothing is connected; the fake client drives dev until creds are added.
Schedule::command('quickbooks:sync')->everyFifteenMinutes()->withoutOverlapping();

// Nightly database backup → gzipped dump uploaded to the S3/MinIO disk under
// backups/, keeping the 14 most recent. The off-store copy + retention is the
// safety net the platform previously lacked. Runs in the background so a slow
// dump never blocks the scheduler.
Schedule::command('db:backup')
    ->dailyAt('02:30')
    ->withoutOverlapping()
    ->runInBackground();

// Nightly: embed any not-yet-indexed records into the AI knowledge base
// (incremental, so it mainly picks up newly SAM.gov-synced opportunities; the
// org's own records re-embed on edit via EmbeddingObserver). Idempotent and
// throttled for the Gemini free tier. Runs at 01:30 Pacific — after the free
// tier's midnight-PT quota reset — so it gets a fresh daily allowance; the
// command's circuit breaker stops cleanly if the day's quota runs out and the
// next night resumes where it left off.
Schedule::command('kb:embed --fresh')
    ->dailyAt('01:30')->timezone('America/Los_Angeles')
    ->withoutOverlapping()->runInBackground();

// Background pipeline refresh: runs every 5 minutes so opportunities stay
// fresh without anyone having the page open. The expired purge runs each
// time; the SAM.gov pull respects pipeline.sync_throttle_minutes to stay
// within SAM's API rate limits.
Schedule::command('pipeline:sync')->everyFiveMinutes()->withoutOverlapping();

// Shipments: refresh active shipments from UPS every 5 minutes (delivered/
// returned are skipped by the command's active() scope; manually-overridden
// shipments with auto_track=false are skipped too).
Schedule::command('mailings:poll')->everyFiveMinutes()->withoutOverlapping();

// Auto-ingest account shipments from UPS Quantum View (only when enabled, so the
// dev simulator never floods the list). New shipments created on the UPS account
// appear automatically; mailings:poll then enriches each with its full timeline.
if (config('services.ups.quantum_view.enabled')) {
    Schedule::command('mailings:ingest')->hourly()->withoutOverlapping();
}
