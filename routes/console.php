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
