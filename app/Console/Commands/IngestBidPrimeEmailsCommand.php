<?php

namespace App\Console\Commands;

use App\Services\BidSources\BidPrime\GmailBidPrimeIngestService;
use App\Services\Email\Gmail\GmailInboxClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class IngestBidPrimeEmailsCommand extends Command
{
    protected $signature = 'bidprime:ingest-email
        {--since= : Days of inbox history to scan (default from config)}
        {--limit= : Max emails to process this run}
        {--reprocess : Re-parse emails already processed}
        {--organization= : Limit to a single organization id}
        {--allow-fake : Permit running against the fake inbox in production}';

    protected $description = 'Read BidPrime alert emails from Gmail and import opportunities.';

    public function handle(GmailBidPrimeIngestService $ingest, GmailInboxClient $client): int
    {
        if (! $client->isConfigured()) {
            // The fake inbox creates demo opportunities — fine for dev/testing,
            // but guard against polluting production by accident.
            if ($this->getLaravel()->isProduction() && ! $this->option('allow-fake')) {
                $this->error('Gmail ingest is not configured (fake inbox). Set GMAIL_INGEST_ENABLED=true + an App Password, or pass --allow-fake.');

                return self::FAILURE;
            }
            $this->warn('Using the FAKE inbox (GMAIL_INGEST_ENABLED is off) — importing demo BidPrime opportunities.');
        }

        try {
            $import = $ingest->ingest(array_filter([
                'since_days' => $this->option('since'),
                'limit' => $this->option('limit'),
                'reprocess' => (bool) $this->option('reprocess'),
                'organization_id' => $this->option('organization'),
            ], fn ($v) => $v !== null && $v !== false && $v !== ''));
        } catch (Throwable $e) {
            $this->error('BidPrime email ingest failed: '.$e->getMessage());
            Log::warning('bidprime:ingest-email failed', ['error' => $e->getMessage()]);

            return self::FAILURE;
        }

        $this->info(sprintf(
            'BidPrime email ingest #%d (%s): %d created, %d updated, %d duplicate, %d errors across %d parsed item(s).',
            $import->id,
            $import->status,
            $import->total_created,
            $import->total_updated,
            $import->total_skipped,
            $import->total_errors,
            $import->total_fetched,
        ));

        return $import->status === 'failed' ? self::FAILURE : self::SUCCESS;
    }
}
