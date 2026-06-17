<?php

namespace App\Console\Commands;

use App\Services\Mailings\MailingIngestService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class IngestMailingsCommand extends Command
{
    protected $signature = 'mailings:ingest {--since=24 : Hours of account history to pull}';

    protected $description = 'Auto-ingest account shipments from UPS Quantum View into mailings.';

    public function handle(MailingIngestService $ingest): int
    {
        $since = now()->subHours((int) $this->option('since'));

        try {
            $r = $ingest->ingest($since);
            $this->info("Ingested {$r['created']} new and updated {$r['updated']} shipment(s).");
        } catch (Throwable $e) {
            $this->error('Quantum View ingest failed: '.$e->getMessage());
            Log::warning('mailings:ingest failed', ['error' => $e->getMessage()]);

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
