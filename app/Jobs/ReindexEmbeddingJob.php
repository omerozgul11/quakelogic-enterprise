<?php

namespace App\Jobs;

use App\Models\DocumentEmbedding;
use App\Services\Ai\KnowledgeBaseService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Re-embeds one record into the knowledge base whenever it's created, updated or
 * deleted — this is what keeps QuakeBot's "memory" current without a full
 * backfill. Dispatched by EmbeddingObserver. Unique per (kind, id) for a short
 * window so a burst of edits collapses to a single re-embed, and skips silently
 * when the active provider has no embeddings capability (so it's a no-op under
 * AI_PROVIDER=fake/anthropic/openai instead of erroring).
 */
class ReindexEmbeddingJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Spread retries out — a failure here is usually a free-tier rate limit. */
    public int $tries = 3;

    public int $backoff = 30;

    /** Collapse repeated edits of the same record within this window (seconds). */
    public int $uniqueFor = 120;

    public function __construct(
        public int $organizationId,
        public string $kind,
        public string $modelClass,
        public int $modelId,
    ) {
        $this->onQueue('ai');
    }

    public function uniqueId(): string
    {
        return "{$this->kind}:{$this->modelId}";
    }

    public function handle(KnowledgeBaseService $kb): void
    {
        // Embeddings off (e.g. provider can't embed) — nothing to do, don't retry.
        if (! $kb->isAvailable()) {
            return;
        }

        /** @var \Illuminate\Database\Eloquent\Model|null $record */
        $record = $this->modelClass::query()->find($this->modelId);

        // Gone (hard- or soft-deleted): drop its chunks so it stops surfacing.
        if (! $record) {
            DocumentEmbedding::where('source_type', $this->kind)
                ->where('source_id', $this->modelId)
                ->delete();

            return;
        }

        $kb->indexRecord($this->organizationId, $this->kind, $record);
    }
}
