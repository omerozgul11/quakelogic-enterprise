<?php

namespace App\Services\Ai;

use App\Models\Agency;
use App\Models\Company;
use App\Models\ComplianceItem;
use App\Models\Contact;
use App\Models\Contract;
use App\Models\DocumentEmbedding;
use App\Models\FollowUp;
use App\Models\Opportunity;
use App\Models\ProposalFile;
use App\Models\ProposalNote;
use App\Models\ProposalSection;
use App\Models\ProposalSubmission;
use App\Services\Documents\DocumentTextExtractionService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Knowledge base / RAG over ALL of the organization's data — proposals and
 * their uploaded files & notes, opportunities, companies, contacts, agencies,
 * follow-ups, contracts and compliance items. Each record's text is chunked and
 * embedded (via the active AI provider) into document_embeddings; at query time
 * we embed the question and rank chunks by cosine similarity in PHP. Used to
 * ground QuakeBot chat answers and the Proposal Writer in the company's real
 * data. No-ops gracefully when the provider can't embed.
 */
class KnowledgeBaseService
{
    private const CHUNK_CHARS = 1200;

    /** Max uploaded-file size we'll extract text from for indexing (bytes). */
    private const MAX_FILE_BYTES = 8_000_000;

    /** All indexable source kinds, in a sensible indexing order. */
    public const KINDS = [
        'proposal', 'proposal_file', 'proposal_note', 'proposal_section', 'opportunity',
        'company', 'contact', 'agency', 'follow_up', 'contract', 'compliance_item',
    ];

    /** @var array<int,string> cache of proposal id => number for file/note labels */
    private array $proposalNumbers = [];

    public function __construct(
        private readonly AiProviderInterface $ai,
        private readonly DocumentTextExtractionService $textExtractor,
    ) {}

    /** Whether the active provider can produce embeddings (probe with a tiny input). */
    public function isAvailable(): bool
    {
        return count($this->ai->embed(['ping'])) === 1;
    }

    /**
     * Org-scoped record stream for a kind (lazy, for the backfill command).
     *
     * @return iterable<int, Model>
     */
    public function recordsFor(int $organizationId, string $kind): iterable
    {
        $orgProposalIds = ProposalSubmission::forOrganization($organizationId)->select('id');

        return match ($kind) {
            'proposal' => ProposalSubmission::forOrganization($organizationId)->cursor(),
            'proposal_file' => ProposalFile::where('is_current_version', true)
                ->whereIn('proposal_submission_id', $orgProposalIds)->cursor(),
            'proposal_note' => ProposalNote::whereIn('proposal_submission_id', $orgProposalIds)->cursor(),
            'proposal_section' => ProposalSection::whereIn('proposal_submission_id', $orgProposalIds)->cursor(),
            'opportunity' => Opportunity::where('organization_id', $organizationId)->cursor(),
            'company' => Company::where('organization_id', $organizationId)->cursor(),
            'contact' => Contact::where('organization_id', $organizationId)->cursor(),
            'agency' => Agency::where('organization_id', $organizationId)->cursor(),
            'follow_up' => FollowUp::where('organization_id', $organizationId)->cursor(),
            'contract' => Contract::where('organization_id', $organizationId)->cursor(),
            'compliance_item' => ComplianceItem::where('organization_id', $organizationId)->cursor(),
            default => [],
        };
    }

    /**
     * Index a single record: (re)build its chunks + embeddings. Returns the
     * number of chunks stored (0 if there's nothing to embed). Throws if the
     * provider can't embed / the call fails, so the queued reindex job retries.
     */
    public function indexRecord(int $organizationId, string $kind, Model $record): int
    {
        return $this->indexRecordsBatch($organizationId, $kind, [$record])[1];
    }

    /**
     * Index many records of one kind in as few embedding calls as possible:
     * every record's chunks are pooled and embedded in groups of $maxPerCall,
     * then each record's rows are rewritten transactionally. This is what makes
     * a full backfill feasible on the Gemini free tier (one HTTP call per ~96
     * chunks instead of one per record). On an embedding failure (e.g. a 429
     * quota hit) nothing in the batch is written and we throw, so the caller can
     * back off and a re-run resumes cleanly (indexing is idempotent).
     *
     * $chunksPerMinute paces successive embedding calls to a target rate (the
     * free tier caps embeddings at ~100/min, each chunk counting as one
     * request). We sleep in proportion to the chunks a call just consumed, so a
     * big call waits longer than a small one and the rolling rate stays under
     * the cap regardless of per-record chunk counts. Throttling lives here at
     * the actual API-call site rather than only between pages.
     *
     * @param  iterable<int,Model>  $records
     * @return array{0:int,1:int}  [recordsIndexed, chunksStored]
     */
    public function indexRecordsBatch(int $organizationId, string $kind, iterable $records, int $maxPerCall = 80, int $chunksPerMinute = 0): array
    {
        // Build each record's chunk list; drop rows for now-empty records.
        $jobs = [];
        foreach ($records as $record) {
            [$label, $text] = $this->corpus($kind, $record);
            $chunks = $this->chunk($text);
            if ($chunks === []) {
                DocumentEmbedding::where('source_type', $kind)->where('source_id', $record->getKey())->delete();
                continue;
            }
            $jobs[] = ['key' => $record->getKey(), 'label' => $label, 'chunks' => $chunks, 'vectors' => []];
        }
        if ($jobs === []) {
            return [0, 0];
        }

        // Pool every chunk (with a back-reference to its record + position) and
        // embed in groups so a record's chunks may span multiple HTTP calls.
        $flat = [];
        foreach ($jobs as $ji => $job) {
            foreach ($job['chunks'] as $ci => $chunk) {
                $flat[] = [$ji, $ci, $chunk];
            }
        }
        foreach (array_chunk($flat, max(1, $maxPerCall)) as $group) {
            $vectors = $this->ai->embed(array_map(fn ($g) => $g[2], $group));
            if (count($vectors) !== count($group)) {
                throw new \RuntimeException(
                    "Embedding failed for {$kind} (got " . count($vectors) . ' of ' . count($group) . ' vectors)'
                );
            }
            foreach ($group as $gi => [$ji, $ci]) {
                $jobs[$ji]['vectors'][$ci] = $vectors[$gi];
            }
            // Pace proportionally to the chunks just sent so the rolling rate
            // stays under the free-tier embed quota (~100/min).
            if ($chunksPerMinute > 0) {
                usleep((int) (count($group) / $chunksPerMinute * 60 * 1_000_000));
            }
        }

        $model = $this->ai->getName();
        $now = now();
        $recordsIndexed = 0;
        $chunksStored = 0;

        foreach ($jobs as $job) {
            if (count($job['vectors']) !== count($job['chunks'])) {
                continue;
            }
            ksort($job['vectors']);
            DB::transaction(function () use ($organizationId, $kind, $job, $model, $now) {
                DocumentEmbedding::where('source_type', $kind)->where('source_id', $job['key'])->delete();
                $rows = [];
                foreach ($job['chunks'] as $i => $chunk) {
                    $rows[] = [
                        'organization_id' => $organizationId,
                        'source_type' => $kind,
                        'source_id' => $job['key'],
                        'source_label' => mb_substr($job['label'], 0, 250),
                        'chunk_index' => $i,
                        'chunk_text' => $chunk,
                        'embedding' => json_encode($job['vectors'][$i]),
                        'model' => $model,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
                foreach (array_chunk($rows, 100) as $batch) {
                    DocumentEmbedding::insert($batch);
                }
            });
            $recordsIndexed++;
            $chunksStored += count($job['chunks']);
        }

        return [$recordsIndexed, $chunksStored];
    }

    /**
     * Source IDs of a kind that already have ≥1 embedding row, for incremental
     * (skip-existing) backfills.
     *
     * @return array<int,bool>  map of source_id => true
     */
    public function existingSourceIds(int $organizationId, string $kind): array
    {
        return DocumentEmbedding::forOrganization($organizationId)
            ->where('source_type', $kind)
            ->distinct()
            ->pluck('source_id')
            ->mapWithKeys(fn ($id) => [(int) $id => true])
            ->all();
    }

    /**
     * Top-k most relevant chunks for a query within an org.
     *
     * @return array<int, array{text:string, source_type:string, source_id:int, label:string, score:float}>
     */
    public function search(int $organizationId, string $query, int $k = 6): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $qv = $this->ai->embed([$query]);
        if (count($qv) !== 1 || $qv[0] === []) {
            return [];
        }
        $queryVec = $qv[0];

        // Stream rows with a cursor so we never hold every embedding vector in
        // memory at once — each row's vector is scored then released. We keep
        // only lightweight {text, label, score} entries for ranking.
        $scored = [];
        $rows = DocumentEmbedding::forOrganization($organizationId)
            ->limit(20000)
            ->select(['source_type', 'source_id', 'source_label', 'chunk_text', 'embedding'])
            ->cursor();

        foreach ($rows as $row) {
            $vec = $row->embedding;
            if (! is_array($vec) || $vec === []) {
                continue;
            }
            $score = $this->cosine($queryVec, $vec);
            if ($score <= 0) {
                continue;
            }
            $scored[] = [
                'text' => $row->chunk_text,
                'source_type' => $row->source_type,
                'source_id' => (int) $row->source_id,
                'label' => $row->source_label ?: ucfirst(str_replace('_', ' ', $row->source_type)),
                'score' => $score,
            ];
        }

        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($scored, 0, $k);
    }

    /**
     * Formatted snippet block for injecting into a prompt, or '' when nothing
     * relevant is found / embeddings are unavailable.
     */
    public function contextFor(int $organizationId, string $query, int $k = 5): string
    {
        $hits = $this->search($organizationId, $query, $k);
        if ($hits === []) {
            return '';
        }

        $lines = array_map(
            fn ($h) => '[' . $h['label'] . "]\n" . mb_substr(trim($h['text']), 0, 800),
            $hits
        );

        return implode("\n\n", $lines);
    }

    /**
     * Build [label, text] for a record of the given kind from its real columns.
     *
     * @return array{0:string,1:string}
     */
    private function corpus(string $kind, Model $r): array
    {
        return match ($kind) {
            'proposal' => [
                trim(($r->proposal_number ?? '') . ' — ' . ($r->project_name ?? 'Proposal'), ' —'),
                $this->join([
                    $r->project_name,
                    $r->solicitation_number ? "Solicitation: {$r->solicitation_number}" : null,
                    $r->scope_summary, $r->description, $r->technical_approach_summary,
                    $r->notes, $r->loss_assessment, $r->lessons_learned,
                ]),
            ],
            'proposal_file' => [
                $this->proposalNumber($r->proposal_submission_id) . ' · ' . ($r->display_name ?? 'Document'),
                $this->join([
                    $r->display_name, $r->document_type, $r->notes,
                    $this->fileText($r),
                ]),
            ],
            'proposal_note' => [
                'Note on ' . $this->proposalNumber($r->proposal_submission_id),
                $this->join([$r->note_type, $r->content]),
            ],
            'proposal_section' => [
                $this->proposalNumber($r->proposal_submission_id) . ' · ' . ($r->heading ?: 'Section'),
                $this->join([$r->heading, $r->content]),
            ],
            'opportunity' => [
                'Opportunity: ' . ($r->title ?? ($r->solicitation_number ?? 'Opportunity')),
                $this->join([
                    $r->title,
                    $r->solicitation_number ? "Solicitation: {$r->solicitation_number}" : null,
                    $r->opportunity_number, $r->agency_name, $r->sub_agency_name,
                    $r->description, $r->scope, $r->requirements_summary, $r->notes, $r->go_no_go_notes,
                ]),
            ],
            'company' => [
                'Company: ' . ($r->name ?? 'Company'),
                $this->join([
                    $r->name, $r->industry, $r->notes, $r->website,
                    trim(implode(' ', array_filter([$r->city, $r->state, $r->country]))),
                    $r->cage_code ? "CAGE: {$r->cage_code}" : null,
                    $r->uei ? "UEI: {$r->uei}" : null,
                ]),
            ],
            'contact' => [
                'Contact: ' . ($r->full_name ?: 'Contact'),
                $this->join([$r->full_name, $r->title, $r->department, $r->email, $r->phone, $r->mobile, $r->notes]),
            ],
            'agency' => [
                'Agency: ' . ($r->name ?? 'Agency'),
                $this->join([$r->name, $r->acronym, $r->agency_type, $r->federal_code, $r->notes,
                    trim(implode(' ', array_filter([$r->city, $r->state])))]),
            ],
            'follow_up' => [
                'Follow-up: ' . ($r->subject ?: ($r->type ?: 'Follow-up')),
                $this->join([$r->subject, $r->type, $r->message]),
            ],
            'contract' => [
                'Contract: ' . ($r->contract_number ?: 'Contract'),
                $this->join([$r->contract_number, $r->po_number, $r->invoice_number, $r->notes]),
            ],
            'compliance_item' => [
                'Compliance: ' . ($r->name ?? 'Item'),
                $this->join([$r->name, is_string($r->type ?? null) ? $r->type : null, $r->identifier, $r->issuer, $r->notes]),
            ],
            default => ['', ''],
        };
    }

    /** Extract text from an uploaded proposal file (size-guarded, best-effort). */
    private function fileText(ProposalFile $file): ?string
    {
        if (empty($file->path) || (int) ($file->size ?? 0) > self::MAX_FILE_BYTES) {
            return null;
        }
        try {
            return $this->textExtractor->extract($file->path, (string) $file->mime_type);
        } catch (\Throwable) {
            return null;
        }
    }

    private function proposalNumber(?int $id): string
    {
        if (! $id) {
            return 'Proposal';
        }
        return $this->proposalNumbers[$id] ??=
            (string) (ProposalSubmission::where('id', $id)->value('proposal_number') ?: "Proposal #{$id}");
    }

    /** @param array<int,?string> $parts */
    private function join(array $parts): string
    {
        return trim(implode("\n\n", array_filter(
            array_map(fn ($p) => is_string($p) ? trim($p) : '', $parts),
            fn ($p) => $p !== '',
        )));
    }

    /**
     * Split text into ~CHUNK_CHARS chunks on paragraph boundaries.
     *
     * @return array<int,string>
     */
    private function chunk(string $text): array
    {
        $text = trim($text);
        if ($text === '') {
            return [];
        }
        if (mb_strlen($text) <= self::CHUNK_CHARS) {
            return [$text];
        }

        $paras = preg_split('/\n{2,}/', $text) ?: [$text];
        $chunks = [];
        $buf = '';
        foreach ($paras as $para) {
            if ($buf !== '' && mb_strlen($buf) + mb_strlen($para) > self::CHUNK_CHARS) {
                $chunks[] = trim($buf);
                $buf = '';
            }
            while (mb_strlen($para) > self::CHUNK_CHARS) {
                $chunks[] = trim(mb_substr($para, 0, self::CHUNK_CHARS));
                $para = mb_substr($para, self::CHUNK_CHARS);
            }
            $buf = $buf === '' ? $para : $buf . "\n\n" . $para;
        }
        if (trim($buf) !== '') {
            $chunks[] = trim($buf);
        }

        return array_values(array_filter($chunks, fn ($c) => $c !== ''));
    }

    /**
     * @param  array<int,float>  $a
     * @param  array<int,float>  $b
     */
    private function cosine(array $a, array $b): float
    {
        $n = min(count($a), count($b));
        if ($n === 0) {
            return 0.0;
        }
        $dot = $na = $nb = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $dot += $a[$i] * $b[$i];
            $na += $a[$i] * $a[$i];
            $nb += $b[$i] * $b[$i];
        }
        if ($na <= 0 || $nb <= 0) {
            return 0.0;
        }

        return $dot / (sqrt($na) * sqrt($nb));
    }
}
