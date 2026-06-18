<?php

namespace App\Console\Commands;

use App\Enums\ProposalStatus;
use App\Models\ProposalSubmission;
use App\Models\User;
use App\Support\Currency;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Restore Gorkem's proposal book from his CSV (owner, status, amount+currency,
 * due + submission dates). Unlike Akin's, most of Gorkem's rows ALREADY exist in
 * the DB — sitting under "Admin" after the wipe recovery, identifiable by exact
 * value match — so this is mostly an in-place update. Each row carries a curated
 * target: an existing proposal id to update, or "new" to create.
 *
 * Two rows correct earlier Akin mis-assignments: #107 (Helium MSLD, INR 5,000,000)
 * is really Gorkem's and is reassigned from Akin. Idempotent via a
 * "[gorkem-folder:NNN]" marker in notes. --dry-run previews; --force skips the prompt.
 */
class ImportGorkemProposalsCommand extends Command
{
    protected $signature = 'proposals:import-gorkem
        {--dry-run : Show what would change without writing}
        {--force : Skip the production-DB confirmation prompt}';

    protected $description = "Restore Gorkem's proposal book from his CSV (update matched existing rows, create the rest).";

    public function handle(): int
    {
        $gorkem = User::where('email', 'gorkem@quakelogic.net')->first() ?? User::where('name', 'Gorkem')->first();
        if (! $gorkem) {
            $this->error('Could not find Gorkem (gorkem@quakelogic.net). Aborting.');

            return self::FAILURE;
        }

        $dry = (bool) $this->option('dry-run');
        $db = DB::connection()->getDatabaseName();
        $this->warn(($dry ? '[dry-run] ' : '') . "Target database: {$db} · Gorkem id: {$gorkem->id} · org: {$gorkem->organization_id}");

        if (! $dry && ! $this->option('force') && ! $this->confirm("Apply Gorkem's CSV (owner/status/value/dates; reassigns #107 from Akin) in [{$db}]?", false)) {
            $this->line('Aborted.');

            return self::SUCCESS;
        }

        $seq = $this->nextSequence($gorkem->organization_id);
        $year = now()->year;
        $updated = 0;
        $created = 0;
        $reassigned = 0;

        foreach ($this->rows() as $row) {
            $fields = $this->fields($row, $gorkem);
            $label = Str::limit($row['title'], 44);

            if ($row['target'] === 'new') {
                // Idempotent: update if this folder was already imported.
                $existing = ProposalSubmission::where('notes', 'like', '%[gorkem-folder:' . $row['folder'] . ']%')->first();
                if ($existing) {
                    $this->line("  refresh ##{$existing->id} (folder {$row['folder']}) \"{$label}\"");
                    if (! $dry) {
                        $existing->forceFill($fields)->save();
                    }
                    $updated++;

                    continue;
                }
                $this->line("  create folder {$row['folder']} \"{$label}\"" . $this->money($fields));
                if (! $dry) {
                    $proposal = ProposalSubmission::create(array_merge($fields, [
                        'organization_id' => $gorkem->organization_id,
                        'created_by' => $gorkem->id,
                        'proposal_manager_id' => $gorkem->id,
                        'proposal_number' => sprintf('QL-%d-%04d', $year, $seq++),
                    ]));
                    $proposal->statusHistory()->create([
                        'changed_by' => $gorkem->id,
                        'from_status' => null,
                        'to_status' => $fields['status']->value,
                        'changed_at' => now(),
                    ]);
                }
                $created++;

                continue;
            }

            $proposal = ProposalSubmission::find((int) $row['target']);
            if (! $proposal) {
                $this->warn("  folder {$row['folder']}: target #{$row['target']} not found — skipped.");

                continue;
            }
            $priorOwner = $proposal->owner_id;
            $note = '';
            if ($priorOwner && (int) $priorOwner !== (int) $gorkem->id && (int) $priorOwner !== 1) {
                $note = "  (reassigned from owner #{$priorOwner})";
                $reassigned++;
            }
            $this->line("  update #{$proposal->id} \"{$label}\"" . $this->money($fields) . $note);
            if (! $dry) {
                $proposal->forceFill($fields)->save();
            }
            $updated++;
        }

        $this->line('');
        $this->info(($dry ? '[dry-run] ' : '') . "Done. Updated {$updated} · created {$created} · of which reassigned-from-another-owner {$reassigned}.");

        return self::SUCCESS;
    }

    /** Column values for a row (shared by update + create). */
    private function fields(array $row, User $gorkem): array
    {
        return [
            'owner_id' => $gorkem->id,
            'status' => ProposalStatus::from($row['status']),
            'project_name' => $row['title'],
            'proposal_value' => $row['value'] !== '' ? (float) $row['value'] : null,
            'currency' => Currency::normalize($row['currency'] !== '' ? $row['currency'] : Currency::DEFAULT),
            'due_date' => $row['due'] !== '' ? $row['due'] : null,
            'submission_date' => $row['submission'] !== '' ? $row['submission'] : null,
            'notes' => '[gorkem-folder:' . $row['folder'] . ']',
        ];
    }

    private function money(array $fields): string
    {
        return $fields['proposal_value']
            ? " [{$fields['currency']} " . number_format((float) $fields['proposal_value']) . ']'
            : '';
    }

    /** Highest existing QL-<year>-NNNN sequence for the org, +1 (collision-safe). */
    private function nextSequence(int $orgId): int
    {
        $year = now()->year;
        $max = ProposalSubmission::withTrashed()
            ->where('organization_id', $orgId)
            ->where('proposal_number', 'like', "QL-{$year}-%")
            ->pluck('proposal_number')
            ->map(fn (string $n) => (int) substr($n, -4))
            ->max();

        return (int) ($max ?? 0) + 1;
    }

    /** @return list<array{folder:string,target:string,status:string,value:string,currency:string,due:string,submission:string,title:string}> */
    private function rows(): array
    {
        $out = [];
        foreach (preg_split('/\R/', trim(self::DATA)) as $line) {
            if (trim($line) === '') {
                continue;
            }
            [$folder, $target, $status, $value, $currency, $due, $submission, $title] = array_pad(explode('|', $line, 8), 8, '');
            $out[] = [
                'folder' => trim($folder), 'target' => trim($target), 'status' => trim($status),
                'value' => trim($value), 'currency' => trim($currency), 'due' => trim($due),
                'submission' => trim($submission), 'title' => trim($title),
            ];
        }

        return $out;
    }

    /**
     * Gorkem's CSV, curated. One row per line:
     *   folder | target(existing id or "new") | status | value | currency | due(Y-m-d) | submission(Y-m-d) | title
     * Ambiguous DD/MM vs MM/DD due dates were resolved using the submission month.
     */
    private const DATA = <<<'DATA'
164|112|submitted|39995|USD|2026-06-17|2026-06-12|Spectrometer for STEM
IAEA|new|submitted|46950|EUR||2026-06-01|IAEA Welding + Cutting All In One
380|110|submitted|||2026-06-08||Bowling Machine RFI
157|108|submitted|43450|EUR|2026-05-18|2026-05-18|Portable Gamma Spectrometer for Saudi Arabia
151|107|submitted|5000000|INR||2026-04-28|Helium Mass Spectrometer Leak Detector (MSLD)
150|106|submitted|146420|USD|2026-05-19|2026-05-16|Trailer Mounted Falling Weight Deflectometer
CANADA|new|submitted|189450|CAD||2026-04-18|Canada Pressmaster
360|109|submitted|25900|USD|2026-04-27|2026-04-25|Air Purifier US Embassy Korea 70 pcs
139|new|submitted|||2026-04-24||Electrical Performance Monitoring System
136|105|submitted|94995|USD||2026-04-13|Saudi Arabia Benchtop EDXRF
120|100|submitted|94995|USD|2026-04-06|2026-03-17|Inductively Coupled Plasma Optical Emission Spectrometer
117|103|submitted|29895|USD|2026-04-01|2026-03-25|Handheld Spectrometer and Associated Equipment
112|new|submitted|||2026-03-23||Handheld X-ray Fluorescence Analyzers (XRFs) RFI
107|101|submitted|149950|CAD|2026-03-27|2026-03-26|3D Printing Robot
113|102|submitted|129995|USD|2026-04-01|2026-03-24|Supply and Installation of X-Ray and WTMD Machines (Syria)
109|new|submitted|94995|USD|2026-03-20|2026-03-17|Optical Emission Spectrometer
100|99|submitted|16500|USD|2026-03-03|2026-02-26|San Antonio Well Inspection Camera
DATA;
}
