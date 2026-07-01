<?php

namespace App\Services\BidSources\BidPrime;

use App\Models\BidprimeEmail;
use App\Models\BidprimeImport;
use App\Models\Organization;
use App\Services\Email\Gmail\GmailInboxClient;
use App\Services\Email\Gmail\GmailMessage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Reads BidPrime alert emails from the Gmail inbox, stores each raw message for
 * audit + reprocessing, parses opportunities out of it, and feeds them through
 * the existing BidPrime dedup/upsert path — recording per-email traceability.
 *
 * Source-client agnostic: drives the fake fixture inbox until live IMAP
 * credentials are configured.
 */
class GmailBidPrimeIngestService
{
    public function __construct(
        private readonly GmailInboxClient $client,
        private readonly BidPrimeEmailParser $parser,
        private readonly BidPrimeImportService $importer,
    ) {}

    /**
     * @param  array{organization_id?:int,since_days?:int,limit?:int,reprocess?:bool}  $options
     */
    public function ingest(array $options = []): BidprimeImport
    {
        $cfg = (array) config('integrations.bidprime.email');
        $organization = $this->resolveOrganization($options['organization_id'] ?? null);
        $reprocess = (bool) ($options['reprocess'] ?? false);
        $sinceDays = (int) ($options['since_days'] ?? $cfg['since_days'] ?? 3);

        $criteria = array_filter([
            'since' => now()->subDays(max(0, $sinceDays)),
            'from_filters' => $cfg['from_filters'] ?? [],
            'subject_filters' => $cfg['subject_filters'] ?? [],
            'limit' => $options['limit'] ?? null,
        ], fn ($v) => $v !== null);

        $import = $this->importer->newRun($organization, [
            'channel' => 'email',
            'inbox' => $this->client->label(),
            'since_days' => $sinceDays,
            'reprocess' => $reprocess,
        ]);
        $createdBy = $this->importer->resolveCreator($organization);

        try {
            foreach ($this->client->fetch($criteria) as $message) {
                $this->ingestMessage($import, $organization, $message, $createdBy, $reprocess);
            }
            $this->importer->finishRun($import, 'completed');
        } catch (\Throwable $e) {
            Log::error('BidPrime email ingest failed', ['error' => $e->getMessage()]);
            $this->importer->finishRun($import, 'failed', $e->getMessage());
        }

        return $import->fresh();
    }

    /** Re-parse a single already-stored email without touching the inbox. */
    public function reprocessEmail(BidprimeEmail $email): BidprimeEmail
    {
        $organization = Organization::findOrFail($email->organization_id);
        $import = $this->importer->newRun($organization, ['channel' => 'email', 'mode' => 'reprocess', 'email_id' => $email->id]);
        $createdBy = $this->importer->resolveCreator($organization);

        $this->parseAndImport($import, $organization, $email, $this->messageFromStored($email), $createdBy);
        $email->update(['bidprime_import_id' => $import->id]);
        $this->importer->finishRun($import, 'completed');

        return $email->fresh();
    }

    private function ingestMessage(BidprimeImport $import, Organization $organization, GmailMessage $message, ?int $createdBy, bool $reprocess): void
    {
        $email = BidprimeEmail::firstOrNew([
            'organization_id' => $organization->id,
            'gmail_message_id' => $message->messageId,
        ]);

        $alreadyProcessed = $email->exists && in_array($email->status, ['parsed', 'no_opportunities'], true);

        // Always keep the freshest raw copy + link to this run.
        $email->fill([
            'gmail_uid' => $message->uid,
            'thread_id' => $message->threadId,
            'from_email' => $message->fromEmail,
            'from_name' => $message->fromName,
            'subject' => Str::limit((string) $message->subject, 990, ''),
            'received_at' => $message->date,
            'raw_html' => $message->html,
            'raw_text' => $message->text,
            'bidprime_import_id' => $import->id,
        ]);

        if ($alreadyProcessed && ! $reprocess) {
            $email->save();

            return;
        }

        $email->save();
        $this->parseAndImport($import, $organization, $email, $message, $createdBy);
    }

    private function parseAndImport(BidprimeImport $import, Organization $organization, BidprimeEmail $email, GmailMessage $message, ?int $createdBy): void
    {
        try {
            $dtos = $this->parser->extractOpportunities($message);
            $count = 0;
            foreach ($dtos as $dto) {
                if ($this->importer->ingestDto($import, $organization, $dto, $createdBy, $email->id) !== 'error') {
                    $count++;
                }
            }

            $email->update([
                'opportunities_found' => $count,
                'status' => $count > 0 ? 'parsed' : 'no_opportunities',
                'parse_error' => null,
                'processed_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('BidPrime email parse failed', ['gmail_message_id' => $message->messageId, 'error' => $e->getMessage()]);
            $email->update([
                'status' => 'failed',
                'parse_error' => Str::limit($e->getMessage(), 1000),
                'processed_at' => now(),
            ]);
        }
    }

    private function messageFromStored(BidprimeEmail $email): GmailMessage
    {
        return new GmailMessage(
            messageId: $email->gmail_message_id ?: ('stored-'.$email->id),
            uid: $email->gmail_uid,
            threadId: $email->thread_id,
            fromEmail: $email->from_email,
            fromName: $email->from_name,
            subject: (string) $email->subject,
            date: $email->received_at,
            html: $email->raw_html,
            text: $email->raw_text,
        );
    }

    private function resolveOrganization(?int $organizationId): Organization
    {
        if ($organizationId) {
            return Organization::findOrFail($organizationId);
        }

        return Organization::orderBy('id')->first()
            ?? throw new \RuntimeException('No organization found for BidPrime email ingest.');
    }
}
