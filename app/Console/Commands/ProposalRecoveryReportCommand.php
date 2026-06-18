<?php

namespace App\Console\Commands;

use App\Models\ProposalSubmission;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Read-only post-wipe triage: for every proposal, report which detail fields are
 * blank and where (if anywhere) they can be recovered from — its surviving
 * uploaded document, the Jun-8 pre-wipe SQL snapshot, or not at all. Writes a CSV
 * to storage/app/recovery/ and prints a summary. Never modifies proposal data.
 */
class ProposalRecoveryReportCommand extends Command
{
    protected $signature = 'proposals:recovery-report';

    protected $description = 'Read-only report of blank proposal fields and their recovery source.';

    /** The 6 proposals fully captured in storage/backups/db_20260608_pre_cleanup.sql. */
    private const DUMP_NUMBERS = ['QL-2024-0001', 'QL-2024-0002', 'QL-2024-0003', 'QL-2024-0004', 'QL-2024-0005', 'QL-2023-0018'];

    public function handle(): int
    {
        $proposals = ProposalSubmission::withTrashed()
            ->with(['agency:id,name', 'company:id,name', 'owner:id,name'])
            ->withCount('files')
            ->orderBy('proposal_number')
            ->get();

        $rows = [];
        $tally = ['document' => 0, 'jun8_dump' => 0, 'partial' => 0, 'unrecoverable' => 0, 'with_docs' => 0, 'missing_value' => 0, 'missing_scope' => 0];

        foreach ($proposals as $p) {
            $hasDoc = $p->files_count > 0;
            $missingValue = $p->proposal_value === null;
            $missingDue = $p->due_date === null;
            $missingScope = $p->scope_summary === null || $p->scope_summary === '';
            $missingDesc = $p->description === null || $p->description === '';
            $inDump = in_array($p->proposal_number, self::DUMP_NUMBERS, true);

            $blankCount = (int) $missingValue + (int) $missingDue + (int) $missingScope + (int) $missingDesc;

            if ($blankCount === 0) {
                $recover = 'complete';
            } elseif ($hasDoc) {
                $recover = 'document';
                $tally['document']++;
            } elseif ($inDump) {
                $recover = 'jun8_dump';
                $tally['jun8_dump']++;
            } elseif ($missingValue && $missingDue && $missingScope) {
                $recover = 'unrecoverable';
                $tally['unrecoverable']++;
            } else {
                $recover = 'partial';
                $tally['partial']++;
            }

            if ($hasDoc) {
                $tally['with_docs']++;
            }
            if ($missingValue) {
                $tally['missing_value']++;
            }
            if ($missingScope) {
                $tally['missing_scope']++;
            }

            $rows[] = [
                $p->id,
                $p->proposal_number,
                $this->clean($p->project_name),
                $this->clean($p->agency?->name ?? ''),
                $this->clean($p->company?->name ?? ''),
                $p->status instanceof \BackedEnum ? $p->status->value : (string) $p->status,
                $p->owner?->name ?? '',
                $p->files_count,
                $missingValue ? 'MISSING' : 'ok',
                $missingDue ? 'MISSING' : 'ok',
                $missingScope ? 'MISSING' : 'ok',
                $missingDesc ? 'MISSING' : 'ok',
                $p->trashed() ? 'yes' : 'no',
                $recover,
            ];
        }

        $header = ['id', 'proposal_number', 'project_name', 'agency', 'company', 'status', 'owner', 'files', 'value', 'due_date', 'scope', 'description', 'deleted', 'recovery_source'];
        $csv = $this->toCsv($header, $rows);

        $path = 'recovery/proposal-recovery-report.csv';
        Storage::disk('local')->put($path, $csv);
        $absolute = Storage::disk('local')->path($path);

        $this->info('Proposal recovery report — ' . count($proposals) . ' proposals');
        $this->line('');
        $this->line('  With a surviving document (re-extractable): ' . $tally['with_docs']);
        $this->line('  Recoverable from documents .............. ' . $tally['document']);
        $this->line('  Recoverable from Jun-8 dump ............. ' . $tally['jun8_dump']);
        $this->line('  Partial (some fields present) ........... ' . $tally['partial']);
        $this->line('  Unrecoverable (no doc, no value) ........ ' . $tally['unrecoverable']);
        $this->line('');
        $this->line('  Missing value: ' . $tally['missing_value'] . '   ·   Missing scope: ' . $tally['missing_scope']);
        $this->line('');
        $this->info('CSV written to: ' . $absolute);

        return self::SUCCESS;
    }

    private function clean(?string $v): string
    {
        return trim(preg_replace('/\s+/', ' ', (string) $v));
    }

    /** @param array<int,array<int,mixed>> $rows */
    private function toCsv(array $header, array $rows): string
    {
        $out = fopen('php://temp', 'r+');
        fputcsv($out, $header);
        foreach ($rows as $row) {
            fputcsv($out, $row);
        }
        rewind($out);

        return (string) stream_get_contents($out);
    }
}
