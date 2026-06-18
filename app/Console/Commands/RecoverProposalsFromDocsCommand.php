<?php

namespace App\Console\Commands;

use App\Models\ProposalFile;
use App\Models\ProposalSubmission;
use App\Services\Ai\AiProviderInterface;
use App\Services\Documents\DocumentTextExtractionService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Post-wipe recovery: re-read each proposal's SURVIVING uploaded document and
 * refill ONLY the blank fields it can recover — proposal_value, due_date,
 * scope_summary (and description when blank). It never overwrites a value that's
 * already set, never touches ownership, and creates no new files/contacts/notes.
 * Idempotent: re-running only ever fills remaining blanks, so it's safe to resume
 * across days if the AI quota runs out (a quota miss just leaves a proposal for
 * the next run).
 */
class RecoverProposalsFromDocsCommand extends Command
{
    protected $signature = 'proposals:recover-from-docs
        {--dry-run : Show what would be filled without writing}
        {--limit=0 : Only process N proposals (0 = all)}
        {--proposal= : Only this proposal id}
        {--sleep=5 : Seconds to pause between proposals (free-tier pacing)}';

    protected $description = 'Recover blank proposal value/dates/scope from surviving uploaded documents (fill-blanks only).';

    private const MAX_VISION_BYTES = 20000000;

    public function handle(AiProviderInterface $ai, DocumentTextExtractionService $text): int
    {
        $dry = (bool) $this->option('dry-run');
        $sleep = max(0, (int) $this->option('sleep'));

        $query = ProposalSubmission::query()
            ->whereHas('files')
            ->where(fn ($q) => $q->whereNull('proposal_value')->orWhereNull('due_date')->orWhereNull('scope_summary'))
            ->with('files')
            ->orderBy('id');

        if ($this->option('proposal')) {
            $query->where('id', (int) $this->option('proposal'));
        }
        if (($limit = (int) $this->option('limit')) > 0) {
            $query->limit($limit);
        }

        $proposals = $query->get();
        $this->info(($dry ? '[dry-run] ' : '') . "Provider: {$ai->getName()} · proposals to scan: {$proposals->count()}");
        $this->line('');

        $filled = 0;
        $empty = 0;

        foreach ($proposals as $i => $proposal) {
            $extracted = $this->extractFromFiles($ai, $text, $proposal);

            if ($extracted === null) {
                $empty++;
                $this->line("  {$proposal->proposal_number}: no data extracted (unreadable doc or AI quota).");
                $this->pace($sleep, $i, $proposals->count());

                continue;
            }

            $updates = $this->blankUpdates($proposal, $extracted);
            if ($updates === []) {
                $this->line("  {$proposal->proposal_number}: nothing new (blanks not found in document).");
                $this->pace($sleep, $i, $proposals->count());

                continue;
            }

            if (! $dry) {
                // forceFill + save: fill blanks only; owner_id is never in $updates.
                $proposal->forceFill($updates)->save();
            }
            $filled++;
            $this->line("  {$proposal->proposal_number}: " . $this->describe($updates));
            $this->pace($sleep, $i, $proposals->count());
        }

        $this->line('');
        $this->info(($dry ? '[dry-run] ' : '') . "Done. Recovered fields for {$filled} proposal(s); {$empty} yielded nothing.");

        return self::SUCCESS;
    }

    /**
     * Extract from the proposal's files, best document first (vision-capable,
     * largest). Returns the first result that carries a usable field, else null.
     *
     * @return array<string,mixed>|null
     */
    private function extractFromFiles(AiProviderInterface $ai, DocumentTextExtractionService $text, ProposalSubmission $proposal): ?array
    {
        $files = $proposal->files
            ->sortByDesc(fn (ProposalFile $f) => [$this->isVisionMime((string) $f->mime_type) ? 1 : 0, (int) ($f->size ?? 0)])
            ->values();

        foreach ($files as $file) {
            $data = $this->extractFile($ai, $text, $file);
            if ($data !== null && $this->hasUsable($data)) {
                return $data;
            }
        }

        return null;
    }

    /** @return array<string,mixed>|null */
    private function extractFile(AiProviderInterface $ai, DocumentTextExtractionService $text, ProposalFile $file): ?array
    {
        $mime = (string) $file->mime_type;

        $bytes = null;
        try {
            $bytes = Storage::disk($file->disk ?: 'local')->get($file->path);
        } catch (\Throwable) {
            $bytes = null;
        }

        // Native vision on the original document (best for cover/price pages).
        if ($ai->supportsVision() && $this->isVisionMime($mime) && (int) ($file->size ?? 0) <= self::MAX_VISION_BYTES && $bytes) {
            try {
                $vision = $ai->extractDocumentVision(base64_encode($bytes), $mime);
                if ($this->hasUsable($vision)) {
                    return $vision;
                }
            } catch (\Throwable) {
                // fall through to text
            }
        }

        // Text fallback.
        try {
            $raw = $text->extract($file->path, $mime);
        } catch (\Throwable) {
            return null;
        }
        if (trim($raw) === '') {
            return null;
        }
        $raw = $text->stripReferenceSections($raw);
        $focus = $text->frontMatter($raw);

        try {
            return $ai->extractDocumentData($focus, ['value', 'due_date', 'scope', 'solicitation_number', 'project_name']);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Build the column updates for fields that are currently blank and that the
     * document supplies. Never includes a field the proposal already has.
     *
     * @param  array<string,mixed>  $e
     * @return array<string,mixed>
     */
    private function blankUpdates(ProposalSubmission $proposal, array $e): array
    {
        $updates = [];

        if (blank($proposal->proposal_value) && ($v = $this->money($e['value'] ?? null)) !== null) {
            $updates['proposal_value'] = $v;
        }
        if (blank($proposal->due_date) && ($d = $this->date($e['due_date'] ?? null)) !== null) {
            $updates['due_date'] = $d;
        }
        if (blank($proposal->scope_summary) && is_string($e['scope'] ?? null) && trim($e['scope']) !== '') {
            $updates['scope_summary'] = trim($e['scope']);
        }
        if (blank($proposal->description) && isset($updates['scope_summary'])) {
            $updates['description'] = $updates['scope_summary'];
        }

        return $updates;
    }

    private function money(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        $n = is_string($value) ? (float) preg_replace('/[^0-9.]/', '', $value) : (float) $value;

        return $n > 0 ? round($n, 2) : null;
    }

    private function date(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }
        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private function describe(array $updates): string
    {
        $bits = [];
        if (isset($updates['proposal_value'])) {
            $bits[] = 'value=$' . number_format((float) $updates['proposal_value']);
        }
        if (isset($updates['due_date'])) {
            $bits[] = 'due=' . $updates['due_date'];
        }
        if (isset($updates['scope_summary'])) {
            $bits[] = 'scope=' . Str::limit($updates['scope_summary'], 50);
        }

        return implode(' · ', $bits);
    }

    private function hasUsable(array $data): bool
    {
        return ! empty($data['value']) || ! empty($data['due_date']) || ! empty($data['scope']);
    }

    private function isVisionMime(string $mime): bool
    {
        return in_array($mime, ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'], true);
    }

    private function pace(int $seconds, int $index, int $total): void
    {
        if ($seconds > 0 && $index < $total - 1) {
            sleep($seconds);
        }
    }
}
