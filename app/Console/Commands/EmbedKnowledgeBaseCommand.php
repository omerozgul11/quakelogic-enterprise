<?php

namespace App\Console\Commands;

use App\Models\Organization;
use App\Services\Ai\KnowledgeBaseService;
use Illuminate\Console\Command;

/**
 * Builds/refreshes the knowledge-base embeddings used for RAG across ALL of an
 * organization's data (proposals, files, notes, opportunities, companies,
 * contacts, agencies, follow-ups, contracts, compliance items). Idempotent —
 * each record's chunks are replaced. Records are pooled and embedded in batches
 * (one HTTP call per ~80 chunks, not per record) and paced to a target rate so a
 * full backfill stays within the Gemini free tier (~100 embeddings/min).
 *
 * The free tier also has a daily ceiling. When that's hit, every call 429s; a
 * circuit breaker stops the run after a few consecutive page failures rather
 * than spinning for hours, and the nightly `--fresh` schedule resumes from where
 * it left off (indexing is idempotent and skips already-embedded records).
 */
class EmbedKnowledgeBaseCommand extends Command
{
    protected $signature = 'kb:embed
        {--org= : Only index this organization id}
        {--kind= : Only index this source kind (e.g. proposal, contact, opportunity)}
        {--fresh : Incremental — only embed records that have no embeddings yet}
        {--page=60 : Records flushed per write batch}
        {--batch=80 : Chunks per embedding API call (keep under the 100/min free-tier cap)}
        {--rate=80 : Target embeddings per minute (free tier allows ~100/min)}';

    protected $description = 'Build/refresh knowledge-base embeddings from all org data (for AI RAG).';

    /** Stop the run after this many consecutive page failures (quota exhausted). */
    private const MAX_CONSECUTIVE_FAILURES = 3;

    public function handle(KnowledgeBaseService $kb): int
    {
        if (! $kb->isAvailable()) {
            $this->error('Embeddings are unavailable right now — either the AI provider has no embeddings capability '
                . '(set AI_PROVIDER=gemini with a GEMINI_API_KEY) or the free-tier daily quota is exhausted '
                . '(resets at midnight Pacific). This run is resumable later with --fresh.');

            return self::FAILURE;
        }

        $orgIds = $this->option('org')
            ? [(int) $this->option('org')]
            : Organization::query()->orderBy('id')->pluck('id')->all();

        $kinds = $this->option('kind')
            ? array_values(array_filter(KnowledgeBaseService::KINDS, fn ($k) => $k === $this->option('kind')))
            : KnowledgeBaseService::KINDS;

        if ($kinds === []) {
            $this->error('Unknown --kind. Valid: ' . implode(', ', KnowledgeBaseService::KINDS));

            return self::FAILURE;
        }

        $fresh = (bool) $this->option('fresh');
        $pageSize = max(1, (int) $this->option('page'));
        $perCall = max(1, (int) $this->option('batch'));
        $rate = max(1, (int) $this->option('rate'));

        $totalRecords = 0;
        $totalChunks = 0;
        $consecutiveFailures = 0;
        $aborted = false;

        foreach ($orgIds as $orgId) {
            if ($aborted) {
                break;
            }
            $this->info("Organization {$orgId}" . ($fresh ? ' (incremental)' : ''));

            foreach ($kinds as $kind) {
                if ($aborted) {
                    break;
                }
                $existing = $fresh ? $kb->existingSourceIds($orgId, $kind) : [];
                $records = 0;
                $chunks = 0;
                $skipped = 0;
                $page = [];

                $flush = function () use (&$page, &$records, &$chunks, &$consecutiveFailures, &$aborted, $kb, $orgId, $kind, $perCall, $rate) {
                    if ($page === [] || $aborted) {
                        $page = [];

                        return;
                    }
                    [$r, $c, $failed] = $this->embedPage($kb, $orgId, $kind, $page, $perCall, $rate);
                    $page = [];
                    $records += $r;
                    $chunks += $c;

                    if ($failed) {
                        $consecutiveFailures++;
                        if ($consecutiveFailures >= self::MAX_CONSECUTIVE_FAILURES) {
                            $this->warn('  Embedding quota appears exhausted (likely the free-tier daily limit). '
                                . 'Stopping — the nightly schedule or a re-run with --fresh will resume from here.');
                            $aborted = true;
                        }
                    } else {
                        $consecutiveFailures = 0;
                    }
                };

                foreach ($kb->recordsFor($orgId, $kind) as $record) {
                    if ($aborted) {
                        break;
                    }
                    if ($fresh && isset($existing[(int) $record->getKey()])) {
                        $skipped++;
                        continue;
                    }
                    $page[] = $record;
                    if (count($page) >= $pageSize) {
                        $flush();
                    }
                }
                $flush();

                if ($records > 0 || $skipped > 0) {
                    $this->line("  {$kind}: {$records} record(s) → {$chunks} chunk(s)"
                        . ($skipped ? ", {$skipped} already embedded" : ''));
                }
                $totalRecords += $records;
                $totalChunks += $chunks;
            }
        }

        $this->newLine();
        $this->info("Done — {$totalChunks} chunk(s) embedded across {$totalRecords} record(s)."
            . ($aborted ? ' (stopped early on quota; resumable)' : ''));

        return self::SUCCESS;
    }

    /**
     * Embed one page of records, retrying a couple of times on transient 429s
     * (the per-minute window clears in ~a minute). Returns [recordsIndexed,
     * chunksStored, failed]; on persistent failure `failed` is true and the page
     * is left unindexed for a later `--fresh` re-run.
     *
     * @param  array<int,\Illuminate\Database\Eloquent\Model>  $page
     * @return array{0:int,1:int,2:bool}
     */
    private function embedPage(KnowledgeBaseService $kb, int $orgId, string $kind, array $page, int $perCall, int $rate): array
    {
        $maxAttempts = 3;
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                [$r, $c] = $kb->indexRecordsBatch($orgId, $kind, $page, $perCall, $rate);

                return [$r, $c, false];
            } catch (\Throwable $e) {
                if ($attempt >= $maxAttempts) {
                    $this->warn("  {$kind}: page failed after {$attempt} tries ({$e->getMessage()})");

                    return [0, 0, true];
                }
                // Transient per-minute quota: wait a full window so it resets.
                $wait = 65;
                $this->warn("  {$kind}: embed retry {$attempt} in {$wait}s ({$e->getMessage()})");
                sleep($wait);
            }
        }

        return [0, 0, true];
    }
}
