<?php

namespace App\Console\Commands;

use App\Enums\ProposalStatus;
use App\Models\ProposalSubmission;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Post-wipe reconciliation of Akin's proposal book. Akin supplied an external
 * list of ~94 proposals (folder#, title, status, federal/non-federal) whose
 * ownership + status were lost in the 2026-06-17 wipe. This command fuzzy-matches
 * each list row to an EXISTING proposal in the DB and — only after review — sets
 * owner = Akin and the status. It NEVER changes project_name, never stores the
 * federal/non-federal category, never creates rows, and never deletes anything.
 *
 * Two modes:
 *   (default)  PREVIEW — read-only. Scores matches, writes a review CSV, prints a
 *              summary grouped by confidence. No DB writes.
 *   --apply    Reads the (reviewed) CSV and writes owner_id + status for rows
 *              marked action=apply. Echoes the target DB; --dry-run simulates.
 */
class ReconcileAkinProposalsCommand extends Command
{
    protected $signature = 'proposals:reconcile-akin
        {--apply : Write owner_id + status from the reviewed CSV (otherwise preview only)}
        {--closest : Force each row to its closest candidate even below threshold (excludes Halil/demo; needs some overlap)}
        {--include-review : Also apply rows marked action=review (any matched row), not just action=apply}
        {--dry-run : With --apply, show the writes without saving}
        {--csv= : CSV path to read/write (default: recovery/akin-reconcile.csv on the local disk)}
        {--force : Skip the production-DB confirmation prompt on --apply}';

    protected $description = "Reconcile Akin's external proposal list to existing DB rows (owner + status only; review-first).";

    private const CSV_PATH = 'recovery/akin-reconcile.csv';

    /** Seeded demo proposals — never a valid target for Akin's real work. */
    private const DEMO = ['QL-2024-0001', 'QL-2024-0002', 'QL-2024-0003', 'QL-2024-0004', 'QL-2024-0005', 'QL-2023-0018'];

    /** Words that add no matching signal — dropped before scoring. */
    private const STOP = [
        'the', 'of', 'and', 'or', 'a', 'an', 'for', 'to', 'with', 'in', 'on', 'at', 'de', 'la', 'due',
        'no', 'one', 'two', 'system', 'systems', 'machine', 'machines', 'equipment', 'supply', 'delivery',
        'installation', 'install', 'training', 'commissioning', 'commission', 'purchase', 'rfp', 'rfq', 'rfi',
        'ifb', 'services', 'service', 'support', 'project', 'new', 'its', 'community', 'technical', 'institute',
        'public', 'office', 'program', 'replacement', 'comparable', 'accessories', 'related', 'analytical',
        'other', 'or equal', 'equal', 'materials', 'support',
    ];

    /** Distinctive equipment nouns — shared ones strongly imply the same proposal. */
    private const EQUIP = [
        'cnc', 'mill', 'milling', 'lathe', 'router', 'plasma', 'waterjet', 'laser', 'press', 'brake', 'tensile',
        'compression', 'utm', 'universal', 'testing', 'spectrometer', 'xrf', 'edxrf', 'icp', 'gcms', 'chromatograph',
        'chromatography', 'mass', 'helium', 'leak', 'engraver', 'engraving', 'welder', 'welding', 'ultrasonic',
        'edm', 'discharge', 'shear', 'guillotine', 'deflectometer', 'analyzer', 'xray', 'turning', 'cmm', 'qpcr',
        'nmr', 'roundness', 'cleaner', 'cleaning', 'marker', 'fiber', 'metal', 'cutter', 'cutting', 'instron',
    ];

    // Score weights / thresholds.
    private const SOLIC_BOOST = 60.0;
    private const JACCARD_WEIGHT = 55.0;
    private const EQUIP_PER = 12.0;
    private const EQUIP_CAP = 25.0;
    private const AUTO_MIN = 65.0;     // primary score to auto-apply
    private const AUTO_GAP = 12.0;     // primary must beat runner-up by this much
    private const REVIEW_MIN = 38.0;   // below this => not_found

    public function handle(): int
    {
        return $this->option('apply') ? $this->apply() : $this->preview();
    }

    private function preview(): int
    {
        $akin = $this->resolveAkin();
        if (! $akin) {
            $this->error('Could not find Akin (akin@quakelogic.net). Aborting.');

            return self::FAILURE;
        }

        $closest = (bool) $this->option('closest');
        $reviewMin = $closest ? 0.01 : self::REVIEW_MIN;

        $list = $this->parseList();
        $proposals = ProposalSubmission::with('owner:id,name')->get();

        // In --closest mode, keep only plausibly-Akin candidates: drop Halil's
        // simulators and the demo records so a forced match can't clobber them.
        $pool = $closest
            ? $proposals->reject(fn (ProposalSubmission $p) => $p->owner?->name === 'Halil' || in_array($p->proposal_number, self::DEMO, true))->values()
            : $proposals;

        $this->info('Akin list rows: ' . count($list) . ' · candidate pool: ' . $pool->count() . ' of ' . $proposals->count() . ($closest ? ' (closest mode: Halil/demo excluded)' : '') . " · Akin user id: {$akin->id}");
        $this->line('');

        // Pre-tokenize DB proposals once.
        $db = $pool->map(fn (ProposalSubmission $p) => [
            'p' => $p,
            'tokens' => $this->tokens((string) $p->project_name),
            'solic' => $this->normSolic((string) $p->solicitation_number),
        ])->all();

        // Per-row ranked candidates.
        $ranked = [];
        foreach ($list as $i => $row) {
            $aTokens = $this->tokens($row['title']);
            $aSolic = $this->normSolic($row['solicitation']);
            $cands = [];
            foreach ($db as $d) {
                $score = $this->score($aTokens, $aSolic, $d['tokens'], $d['solic']);
                $keep = $score >= self::REVIEW_MIN;
                if (! $keep && $closest && $score > 0) {
                    // Below the normal bar a forced match is only allowed when the two
                    // share an actual EQUIPMENT keyword — incidental words (center,
                    // air, testing, monitoring) must not pull in unrelated proposals.
                    $keep = count(array_intersect($this->equipPresent($aTokens), $this->equipPresent($d['tokens']))) > 0;
                }
                if ($keep) {
                    $cands[] = ['pid' => $d['p']->id, 'score' => $score, 'p' => $d['p']];
                }
            }
            usort($cands, fn ($x, $y) => $y['score'] <=> $x['score']);
            $ranked[$i] = $cands;
        }

        // Greedy 1:1 global assignment over all (row, candidate) pairs.
        $pairs = [];
        foreach ($ranked as $i => $cands) {
            foreach ($cands as $c) {
                if ($c['score'] >= $reviewMin) {
                    $pairs[] = ['row' => $i, 'pid' => $c['pid'], 'score' => $c['score'], 'p' => $c['p']];
                }
            }
        }
        usort($pairs, fn ($x, $y) => $y['score'] <=> $x['score']);
        $assignRow = [];
        $takenPid = [];
        foreach ($pairs as $pair) {
            if (isset($assignRow[$pair['row']]) || isset($takenPid[$pair['pid']])) {
                continue;
            }
            $assignRow[$pair['row']] = $pair;
            $takenPid[$pair['pid']] = $pair['row'];
        }

        $rows = [];
        $counts = ['apply' => 0, 'review' => 0, 'not_found' => 0];
        $auto = [];
        $review = [];
        $notFound = [];

        foreach ($list as $i => $row) {
            $primary = $assignRow[$i] ?? null;
            $runnerUp = $this->runnerUpScore($ranked[$i], $primary['pid'] ?? null);

            if ($primary && $primary['score'] >= self::AUTO_MIN && ($primary['score'] - $runnerUp) >= self::AUTO_GAP) {
                $action = 'apply';
            } elseif ($primary) {
                $action = 'review';
            } else {
                $action = 'not_found';
            }
            $counts[$action]++;

            $p = $primary['p'] ?? null;
            $alts = $this->altNote($ranked[$i], $primary['pid'] ?? null);

            $rows[] = [
                $row['folder'],
                $row['title'],
                $row['status'],
                $p?->id ?? '',
                $p?->proposal_number ?? '',
                $p ? Str::limit((string) $p->project_name, 60) : '',
                $p?->owner?->name ?? '',
                $p?->status?->value ?? '',
                $primary ? (string) $primary['score'] : '',
                $action,
                $alts,
            ];

            $line = sprintf('  %-5s %-44s', $row['folder'], Str::limit($row['title'], 44));
            if ($action === 'apply') {
                $auto[] = $line . sprintf(' => #%d %s [%s, owner:%s] score=%s', $p->id, Str::limit((string) $p->project_name, 34), $p->status?->value, $p->owner?->name ?? '-', $primary['score']);
            } elseif ($action === 'review') {
                $review[] = $line . sprintf(' ~ #%d %s score=%s | %s', $p->id, Str::limit((string) $p->project_name, 30), $primary['score'], $alts);
            } else {
                $notFound[] = $line . ' (no candidate >= ' . $reviewMin . ')';
            }
        }

        $this->writeCsv($rows);

        $this->renderGroup('AUTO — high-confidence, will apply on --apply', $auto, 'info');
        $this->renderGroup('REVIEW — confirm/correct matched_proposal_id, set action=apply to include', $review, 'comment');
        $this->renderGroup('NOT FOUND — no DB match (likely lost in the wipe; import was declined)', $notFound, 'warn');

        $this->line('');
        $this->info("Summary: AUTO {$counts['apply']} · REVIEW {$counts['review']} · NOT FOUND {$counts['not_found']}");
        $this->line('CSV written: ' . Storage::disk('local')->path($this->csvPath()));
        $this->line('Review/edit the CSV (set action=apply + matched_proposal_id where needed), then run with --apply.');

        return self::SUCCESS;
    }

    private function apply(): int
    {
        $akin = $this->resolveAkin();
        if (! $akin) {
            $this->error('Could not find Akin (akin@quakelogic.net). Aborting.');

            return self::FAILURE;
        }

        $dry = (bool) $this->option('dry-run');
        $db = DB::connection()->getDatabaseName();
        $this->warn(($dry ? '[dry-run] ' : '') . "Target database: {$db}");

        if (! $dry && ! $this->option('force') && ! $this->confirm("Write owner=Akin + status to matched rows in [{$db}]?", false)) {
            $this->line('Aborted.');

            return self::SUCCESS;
        }

        $path = $this->csvPath();
        if (! Storage::disk('local')->exists($path)) {
            $this->error('CSV not found: ' . Storage::disk('local')->path($path) . '. Run the preview first.');

            return self::FAILURE;
        }

        $records = $this->readCsv($path);
        $changed = 0;
        $skipped = 0;
        $noop = 0;

        $includeReview = (bool) $this->option('include-review');
        foreach ($records as $r) {
            $action = $r['action'] ?? '';
            $allowed = $action === 'apply' || ($includeReview && $action === 'review');
            if (! $allowed || ! ctype_digit((string) ($r['matched_proposal_id'] ?? ''))) {
                $skipped++;

                continue;
            }
            $status = $this->statusEnum($r['list_status'] ?? '');
            if (! $status) {
                $this->warn("  folder {$r['folder']}: unknown status '{$r['list_status']}' — skipped.");
                $skipped++;

                continue;
            }

            $proposal = ProposalSubmission::find((int) $r['matched_proposal_id']);
            if (! $proposal) {
                $this->warn("  folder {$r['folder']}: proposal #{$r['matched_proposal_id']} not found — skipped.");
                $skipped++;

                continue;
            }

            $needsOwner = (int) $proposal->owner_id !== (int) $akin->id;
            $needsStatus = $proposal->status !== $status;
            if (! $needsOwner && ! $needsStatus) {
                $noop++;

                continue;
            }

            $before = sprintf('owner:%s status:%s', $proposal->owner_id, $proposal->status?->value);
            if (! $dry) {
                // Only these two columns. project_name is never touched.
                $proposal->forceFill(['owner_id' => $akin->id, 'status' => $status])->save();
            }
            $changed++;
            $this->line(sprintf('  #%d %s: %s -> owner:%d status:%s', $proposal->id, Str::limit((string) $proposal->project_name, 40), $before, $akin->id, $status->value));
        }

        $this->line('');
        $this->info(($dry ? '[dry-run] ' : '') . "Done. Updated {$changed} · already-correct {$noop} · skipped {$skipped}.");

        return self::SUCCESS;
    }

    private function resolveAkin(): ?User
    {
        return User::where('email', 'akin@quakelogic.net')->first()
            ?? User::where('name', 'Akin')->first();
    }

    private function statusEnum(string $value): ?ProposalStatus
    {
        return match (strtolower(trim($value))) {
            'submitted' => ProposalStatus::Submitted,
            'lost' => ProposalStatus::Lost,
            default => null,
        };
    }

    // --- Matching ----------------------------------------------------------

    /** @return list<string> */
    private function tokens(string $text): array
    {
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9 ]+/', ' ', $text);
        $parts = preg_split('/\s+/', trim((string) $text), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_filter(
            $parts,
            // Drop short words, stop words, and pure numbers (dates/quantities add only noise).
            fn (string $t) => strlen($t) > 1 && ! ctype_digit($t) && ! in_array($t, self::STOP, true)
        ));
    }

    private function normSolic(string $raw): string
    {
        return preg_replace('/[^a-z0-9]/', '', strtolower($raw)) ?? '';
    }

    /**
     * @param  list<string>  $aTokens
     * @param  list<string>  $bTokens
     */
    private function score(array $aTokens, string $aSolic, array $bTokens, string $bSolic): float
    {
        $solBoost = 0.0;
        if ($aSolic !== '' && $bSolic !== '' && strlen($aSolic) >= 6
            && ($aSolic === $bSolic || str_contains($bSolic, $aSolic) || str_contains($aSolic, $bSolic))) {
            $solBoost = self::SOLIC_BOOST;
        }

        $a = array_values(array_unique($aTokens));
        $b = array_values(array_unique($bTokens));
        if ($a === [] || $b === []) {
            return round($solBoost, 1);
        }

        $inter = count(array_intersect($a, $b));
        $union = count(array_unique(array_merge($a, $b))) ?: 1;
        $jaccard = $inter / $union;

        $equipShared = count(array_intersect($this->equipPresent($a), $this->equipPresent($b)));
        $equipBoost = min(self::EQUIP_CAP, $equipShared * self::EQUIP_PER);

        return round(min(100.0, $solBoost + $jaccard * self::JACCARD_WEIGHT + $equipBoost), 1);
    }

    /**
     * Equipment keywords "present" in a token set — exact, or as a prefix so the
     * renamed product names match (e.g. "press" -> "presspro", "plasma" -> "plasmaforge").
     *
     * @param  list<string>  $tokens
     * @return list<string>
     */
    private function equipPresent(array $tokens): array
    {
        $present = [];
        foreach (self::EQUIP as $e) {
            foreach ($tokens as $t) {
                if ($t === $e || (strlen($e) >= 4 && str_starts_with($t, $e))) {
                    $present[] = $e;
                    break;
                }
            }
        }

        return $present;
    }

    /** @param list<array{pid:int,score:float,p:ProposalSubmission}> $cands */
    private function runnerUpScore(array $cands, ?int $excludePid): float
    {
        foreach ($cands as $c) {
            if ($c['pid'] !== $excludePid) {
                return $c['score'];
            }
        }

        return 0.0;
    }

    /** @param list<array{pid:int,score:float,p:ProposalSubmission}> $cands */
    private function altNote(array $cands, ?int $excludePid): string
    {
        $alts = [];
        foreach ($cands as $c) {
            if ($c['pid'] === $excludePid) {
                continue;
            }
            $alts[] = sprintf('#%d(%s) %s', $c['pid'], $c['score'], Str::limit((string) $c['p']->project_name, 24));
            if (count($alts) >= 2) {
                break;
            }
        }

        return $alts ? 'alts: ' . implode(' ; ', $alts) : '';
    }

    // --- The external list -------------------------------------------------

    /** @return list<array{folder:string,status:string,category:string,title:string,solicitation:string}> */
    private function parseList(): array
    {
        $out = [];
        foreach (preg_split('/\R/', trim(self::DATA)) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            [$folder, $status, $category, $raw] = array_pad(explode('|', $line, 4), 4, '');
            $out[] = [
                'folder' => trim($folder),
                'status' => trim($status),
                'category' => trim($category),
                'title' => trim($raw),
                'solicitation' => $this->extractSolic($raw),
            ];
        }

        return $out;
    }

    private function extractSolic(string $raw): string
    {
        if (preg_match_all('/\b[0-9A-Z]{6,}\b/', $raw, $m)) {
            foreach ($m[0] as $tok) {
                if (preg_match('/[0-9]/', $tok) && preg_match('/[A-Z]/', $tok)) {
                    return $tok;
                }
            }
        }

        return '';
    }

    // --- CSV ---------------------------------------------------------------

    private function csvPath(): string
    {
        return (string) ($this->option('csv') ?: self::CSV_PATH);
    }

    /** @param list<array<int,mixed>> $rows */
    private function writeCsv(array $rows): void
    {
        $header = ['folder', 'your_title', 'list_status', 'matched_proposal_id', 'matched_proposal_number', 'matched_db_name', 'current_owner', 'current_status', 'score', 'action', 'notes'];
        $out = fopen('php://temp', 'r+');
        fputcsv($out, $header);
        foreach ($rows as $row) {
            fputcsv($out, $row);
        }
        rewind($out);
        Storage::disk('local')->put($this->csvPath(), (string) stream_get_contents($out));
    }

    /** @return list<array<string,string>> */
    private function readCsv(string $path): array
    {
        $raw = (string) Storage::disk('local')->get($path);
        $fh = fopen('php://temp', 'r+');
        fwrite($fh, $raw);
        rewind($fh);

        $header = fgetcsv($fh);
        if (! $header) {
            return [];
        }
        $records = [];
        while (($line = fgetcsv($fh)) !== false) {
            $records[] = array_combine($header, array_pad($line, count($header), ''));
        }

        return $records;
    }

    /** @param list<string> $lines */
    private function renderGroup(string $title, array $lines, string $style): void
    {
        $this->line('');
        $this->{$style === 'warn' ? 'warn' : ($style === 'comment' ? 'comment' : 'info')}("=== {$title} (" . count($lines) . ') ===');
        foreach ($lines as $l) {
            $this->line($l);
        }
    }

    /**
     * Akin's external list, transcribed verbatim. One row per line:
     *   folder | status | category | raw-title (solicitation/place/date as given)
     */
    private const DATA = <<<'DATA'
116|submitted|FEDERAL|140R8125Q02111 - TSC IIJA 8560 CNC MILL AND LATHE - due 7:28
148|submitted|FEDERAL|UOT202517342 - Vertical Computer Numerical Control (CNC) Machining System University of Toronto - due 8:7
378|submitted|FEDERAL|Waterjet 06/10
375|submitted|FEDERAL|CNC Ultrasonic Machining Center 06/05
391|submitted|FEDERAL|Epilog Fusion CNC CO2 Laser or Equal 06/15
374|submitted|FEDERAL|Press Brake 06/05
387|submitted|FEDERAL|CO2 Laser Cutter & Engraver 06/08
384|submitted|FEDERAL|CNC Arc Plasma Cutting 06/10
373|submitted|FEDERAL|2 Knee Type Milling Machine 06/05
363|submitted|FEDERAL|Dual Laser Engraver 04/30
340|submitted|FEDERAL|CO2 Laser Cutter & Engraver 03/16
321|submitted|FEDERAL|(1) X-Ray Luggage Scanner in Quito 03/11
312|submitted|FEDERAL|Milling Machine LADW 02/11
294|submitted|FEDERAL|CNC Router Cutting System 02/09
298|submitted|FEDERAL|Plasma Cutting Machine 01/31
272|submitted|FEDERAL|Large Roundness Machine 01/16
279|submitted|FEDERAL|1200W Laser Welder / CNC Cutter 01/14
215|submitted|FEDERAL|Vertical Milling Machine Dept of Army 11/10
156|submitted|FEDERAL|M6700425Q0051 - Horizontal Machining Center with Single Table 08 29
151|submitted|FEDERAL|1232SA25Q0548 - Instron 34SC-5 Materials Testing System, or comparable - due 8:11
183|submitted|FEDERAL|140R8125Q0339 - TSC IIJA 8560 CNC MILL AND LATHE 09 16
166|submitted|FEDERAL|W912K625QA018 - Waterjet Cutting Machine 09 12
370|submitted|NON-FEDERAL|Laser Welding and Cutting Machine IAEA 06/01
381|submitted|NON-FEDERAL|CNC Hawaii 06/05
377|submitted|NON-FEDERAL|Tensile Testing Machine Installation 06/04
361|submitted|NON-FEDERAL|Concrete Compression Machine 05/15
354|submitted|NON-FEDERAL|Laser Engraing 04/08
118|submitted|NON-FEDERAL|Gas Chromotograph Mass Spectrometer - portal - 09/04
248|submitted|NON-FEDERAL|CNC Tube Bending Machine 12/17 Ontario
305|submitted|NON-FEDERAL|Mass Helium Leak Detector 02/17
295|submitted|NON-FEDERAL|CNC Cutting System Rhode Island 02/12
010|submitted|NON-FEDERAL|Carbon Handheld Analyzer 02/10
110|submitted|NON-FEDERAL|ED XRF Spectrometer Chile IAEA 01/16
290|submitted|NON-FEDERAL|CNC Plasma Cutting Systems 01/23
100|submitted|NON-FEDERAL|Chromatography System with a Mass Spectrometer, Uni of Iowa 12/30
229|submitted|NON-FEDERAL|CNC Lathe Machine Mississippi Gulf Coast Community College 11/19
224|submitted|NON-FEDERAL|Laser Welder 11/06
179|submitted|NON-FEDERAL|CNC Machine for Its Fabrication Lab University of Oregon 09/15
150|lost||District Shops Body Shop 130-Ton Hydraulic Press Brake Replacement - due 8:20
362|lost||Laser Cleaner 04/29
351|lost||Press Brake 04/30
361|lost||CNC Milling Machine AIR FORCE 04/24
357|lost||LASER CORRISION CONTROL CLEANING MACHINE 04/24
126|lost||Purchase of CNC Machines and Accessories - BBB Grant - 10/04
337|lost||UTM South Carolina 03/31
331|lost||Ingham ISD - Plasma Cutting Table 03/19
314|lost||CNC Mill and Lathe Machines, Korea, 03/03
012|lost||Laser Cleaner 02/05
311|lost||RFP - ARSD CNC Router 02/16
283|lost||Waterjet Cutting Machine - Machine Shop 01/19
274|lost||CNC Plasma Cutting System Mountain Home Public Schools 01/26
289|lost||CNC Milling Station SANDIA 01/13
287|lost||Compact Enclosed Metal Cutting Fiber Laser 01/19
255|lost||Hydraulic CNC Guillotine Shear 12/16
280|lost||Purchase of Precision Press Metal Brake 12/30
271|lost||Tensile Testing Machine with High-Temperature Testing System 01/07
259|lost||Laser Engraving System CANADA 12/17
258|lost||CNC Router Fox Valley Technical College 12/22
253|lost||Hydraulic Press Brake Machine 12/10
251|lost||CNC Lathe Machine Northeast Iowa Community College 12/18
250|lost||CNC SmartShop 2 Pro Computer Controlled Cutting Flatbed 12/11
247|lost||Fiber Laser Marker Alpena Community College 12/01
241|lost||Laser Engraver AB, CA 11/25
246|lost||CNC Fiber Laser Cutting Table Lake Area Technical Institute 12/01
240|lost||Electrical Discharge Machine 11/18
214|lost||Milling Machine Binghamton University 11/13
227|lost||Compression and Tension Testing Equipment 11/12
238|lost||Knee Milling Machine Texas A&M 11/12
164|lost||140G0125Q0210 - METAL LATHE 08 28
219|lost||Inductively Coupled Plasma Optical Emission Spectrometer (ICP-OES) 11/12
236|lost||100KN Universal Testing Machine 11/20
223|lost||5 Axis CNC Mill 11/05
197|lost||100KN Universal Testing Machine 10/14
208|lost||CNC Turning Machine 11/05
159|lost||3-Axis CNC Mill 08/22
211|lost||Compression Machine 10/30
182|lost||W9132T25QA009 - Fiber Laser Cutter 09 17
202|lost||United Nations Office CMM, CNC Machines, Plasma Cutter, Universal Lathe 10/20
206|lost||One CNC Plasma Table, Support Materials and Training Construction 10/20
201|lost||CNC Machine Compact Toolroom Del Mare College 10/16
192|lost||CNC Router System 10/03
193|lost||CNC Router System & Accessories 10/02
169|lost||Spectrometer with other analytical equipment 09/05
191|lost||W519TC25QA080 - CNC Lathe 09 30
181|lost||75F40125Q00437 - 3-Axis Milling Machine 09 17
152|lost||W51AA125Q0070 - CNC FIBER LASER CUTTING MACHINE Army Tobyhanna Depot PA 09 03
163|lost||W912K625QA016 - CNC Milling and Lathe Machine 09 19
184|lost||1232SA25Q0864 - Real-Time Quantitative Polymerase Chain Reaction qPCR Machine 09 16
157|lost||140R8125Q0248 - LATHE 08 20
158|lost||M6890925Q7913 - Fiber Laser Cutter USMC Camp Pendleton CA 08 25
160|lost||W912JB25QA088 - CNC Mill Machine 09 02
175|lost||W911S225U1893 - CNC Plasma Table 09 10
177|lost||UTM South Carolina Uni 09/10
170|lost||Nuclear Magnetic Resonance Spectrometer 09/30
DATA;
}
