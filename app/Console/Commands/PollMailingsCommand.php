<?php

namespace App\Console\Commands;

use App\Models\ProposalMailing;
use App\Services\Mailings\MailingTrackingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class PollMailingsCommand extends Command
{
    protected $signature = 'mailings:poll {--id= : Refresh a single mailing by id}';

    protected $description = 'Refresh active mailings from the UPS Tracking API (skips delivered/returned).';

    public function handle(MailingTrackingService $tracking): int
    {
        $query = ProposalMailing::query()->active();
        if ($id = $this->option('id')) {
            $query->whereKey($id);
        }

        $refreshed = 0;
        $failed = 0;

        $query->orderBy('id')->chunkById(100, function ($mailings) use ($tracking, &$refreshed, &$failed) {
            foreach ($mailings as $mailing) {
                try {
                    $fresh = $tracking->refresh($mailing);
                    $tracking->alertAtRiskIfNeeded($fresh);
                    $refreshed++;
                } catch (Throwable $e) {
                    $failed++;
                    Log::warning('mailings:poll failed for a mailing', [
                        'mailing_id' => $mailing->id,
                        'tracking' => $mailing->ups_tracking_number,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        });

        $this->info("Refreshed {$refreshed} mailing(s), {$failed} failed.");

        return self::SUCCESS;
    }
}
