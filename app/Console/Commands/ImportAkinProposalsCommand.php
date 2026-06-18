<?php

namespace App\Console\Commands;

use App\Enums\ProposalStatus;
use App\Models\ProposalSubmission;
use App\Models\User;
use App\Support\Currency;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Restore Akin's full proposal book from his authoritative CSV (94 rows: name,
 * status, proposal amount, due + submission dates). It:
 *   1. REVERTS the earlier fuzzy "closest" attachments — returns those
 *      QL-product / spectrometer rows to their pre-session owner + status.
 *   2. UPDATES the handful of genuine existing matches in place (rename + data).
 *   3. CREATES every other row as a new proposal owned by Akin with the CSV data.
 *
 * Idempotent: each managed proposal is stamped with a "[akin-folder:NNN]" marker
 * in notes, so re-running updates rather than duplicates. Category is ignored
 * (per the user). --dry-run previews; --force skips the prod-DB confirmation.
 */
class ImportAkinProposalsCommand extends Command
{
    protected $signature = 'proposals:import-akin
        {--dry-run : Show what would change without writing}
        {--force : Skip the production-DB confirmation prompt}';

    protected $description = "Restore Akin's proposal book from his CSV: revert approximate matches, update genuine ones, create the rest.";

    private const ADMIN_ID = 1;

    /** Genuine existing matches — update in place (CSV folder => proposal id). */
    private const GENUINE = [
        '219' => 104, // Inductively Coupled Plasma Optical Emission Spectrometer (ICP-OES)
        '010' => 134, // Carbon Handheld Analyzer
        '305' => 107, // Helium Mass Spectrometer Leak Detector
        '370' => 111, // Laser Welding Machine
    ];

    /**
     * Approximate "closest" attachments to undo — proposal id => [owner_id, status]
     * to restore (their pre-session state). Akin id 5, Admin id 1.
     */
    private const REVERT = [
        68 => [self::ADMIN_ID, 'awarded'],
        129 => [self::ADMIN_ID, 'submitted'],
        121 => [5, 'submitted'],
        128 => [5, 'submitted'],
        135 => [self::ADMIN_ID, 'submitted'],
        118 => [self::ADMIN_ID, 'submitted'],
        100 => [self::ADMIN_ID, 'lost'],
        124 => [self::ADMIN_ID, 'submitted'],
        103 => [self::ADMIN_ID, 'lost'],
        136 => [self::ADMIN_ID, 'submitted'],
        130 => [5, 'submitted'],
        112 => [self::ADMIN_ID, 'submitted'],
        108 => [self::ADMIN_ID, 'submitted'],
    ];

    public function handle(): int
    {
        $akin = User::where('email', 'akin@quakelogic.net')->first() ?? User::where('name', 'Akin')->first();
        if (! $akin) {
            $this->error('Could not find Akin (akin@quakelogic.net). Aborting.');

            return self::FAILURE;
        }

        $dry = (bool) $this->option('dry-run');
        $db = DB::connection()->getDatabaseName();
        $this->warn(($dry ? '[dry-run] ' : '') . "Target database: {$db} · Akin id: {$akin->id} · org: {$akin->organization_id}");

        if (! $dry && ! $this->option('force') && ! $this->confirm("Revert approx. matches, update genuine, and create the rest in [{$db}]?", false)) {
            $this->line('Aborted.');

            return self::SUCCESS;
        }

        $rows = $this->parse();

        // 1) Revert approximate attachments.
        $reverted = 0;
        $this->info(($dry ? '[dry-run] ' : '') . '— Reverting approximate matches —');
        foreach (self::REVERT as $id => [$ownerId, $status]) {
            $p = ProposalSubmission::find($id);
            if (! $p) {
                continue;
            }
            $statusEnum = ProposalStatus::from($status);
            if ((int) $p->owner_id === $ownerId && $p->status === $statusEnum) {
                continue;
            }
            $this->line("  revert #{$id} {$this->short($p->project_name)} -> owner:{$ownerId} status:{$status}");
            if (! $dry) {
                $p->forceFill(['owner_id' => $ownerId, 'status' => $statusEnum])->save();
            }
            $reverted++;
        }

        // 2) Update genuine existing matches in place.
        $updated = 0;
        $this->info(($dry ? '[dry-run] ' : '') . '— Updating genuine matches —');
        foreach (self::GENUINE as $folder => $id) {
            $row = $rows[$folder] ?? null;
            $p = ProposalSubmission::find($id);
            if (! $row || ! $p) {
                continue;
            }
            $fields = $this->fields($row, $akin);
            $this->line("  update #{$id} -> \"{$this->short($fields['project_name'])}\" [{$row['status']}" . ($fields['proposal_value'] ? ", {$fields['currency']} " . number_format($fields['proposal_value']) : '') . ']');
            if (! $dry) {
                $p->forceFill($fields)->save();
            }
            $updated++;
        }

        // 3) Create (or update-by-marker) everything else. The canonical
        // ProposalNumberService is count-based and collides with the recovered,
        // non-contiguous QL-2026 numbers, so for this one-time backfill we issue
        // numbers from the current MAX sequence + 1 (guaranteed unique).
        $seq = $this->nextSequence($akin->organization_id);
        $year = now()->year;
        $created = 0;
        $refreshed = 0;
        $this->info(($dry ? '[dry-run] ' : '') . '— Creating the rest —');
        foreach ($rows as $folder => $row) {
            if (isset(self::GENUINE[$folder])) {
                continue;
            }
            $fields = $this->fields($row, $akin);
            $existing = ProposalSubmission::where('notes', 'like', '%[akin-folder:' . $folder . ']%')->first();

            if ($existing) {
                $this->line("  refresh ##{$existing->id} (folder {$folder}) \"{$this->short($fields['project_name'])}\"");
                if (! $dry) {
                    $existing->forceFill($fields)->save();
                }
                $refreshed++;

                continue;
            }

            $summary = "  create folder {$folder} \"{$this->short($fields['project_name'])}\" [{$row['status']}" . ($fields['proposal_value'] ? ", {$fields['currency']} " . number_format($fields['proposal_value']) : '') . ($fields['due_date'] ? ", due {$fields['due_date']}" : '') . ']';
            $this->line($summary);

            if (! $dry) {
                $proposal = ProposalSubmission::create(array_merge($fields, [
                    'organization_id' => $akin->organization_id,
                    'created_by' => $akin->id,
                    'proposal_manager_id' => $akin->id,
                    'proposal_number' => sprintf('QL-%d-%04d', $year, $seq++),
                ]));
                $proposal->statusHistory()->create([
                    'changed_by' => $akin->id,
                    'from_status' => null,
                    'to_status' => $fields['status']->value,
                    'changed_at' => now(),
                ]);
            }
            $created++;
        }

        $this->line('');
        $this->info(($dry ? '[dry-run] ' : '') . "Done. Reverted {$reverted} · updated {$updated} · created {$created} · refreshed {$refreshed} · total rows " . count($rows) . '.');

        return self::SUCCESS;
    }

    /**
     * Column values from a CSV row (shared by update + create). owner_id + status
     * are always set to Akin / the row's status; name/value/dates as parsed.
     *
     * @param  array<string,string>  $row
     * @return array<string,mixed>
     */
    private function fields(array $row, User $akin): array
    {
        [$value, $currency] = $this->money($row['value']);
        $solic = $this->solic($row['title']);
        $name = $this->cleanTitle($row['title'], $solic);

        return [
            'owner_id' => $akin->id,
            'status' => ProposalStatus::from($row['status']),
            'project_name' => $name,
            'solicitation_number' => $solic ?: null,
            'proposal_value' => $value,
            'currency' => Currency::normalize($currency ?? Currency::DEFAULT),
            'due_date' => $this->date($row['due']),
            'submission_date' => $this->date($row['submission']),
            'notes' => '[akin-folder:' . $row['folder'] . ']',
        ];
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

    /** @return array<string,array{folder:string,status:string,value:string,due:string,submission:string,title:string}> */
    private function parse(): array
    {
        $out = [];
        foreach (preg_split('/\R/', trim(self::DATA)) as $line) {
            if (trim($line) === '') {
                continue;
            }
            [$folder, $status, $value, $due, $submission, $title] = array_pad(explode('|', $line, 6), 6, '');
            $out[trim($folder)] = [
                'folder' => trim($folder),
                'status' => trim($status),
                'value' => trim($value),
                'due' => trim($due),
                'submission' => trim($submission),
                'title' => trim($title),
            ];
        }

        return $out;
    }

    private function solic(string $raw): string
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

    private function cleanTitle(string $raw, string $solic): string
    {
        $name = $raw;
        if ($solic !== '') {
            $name = preg_replace('/^\s*' . preg_quote($solic, '/') . '\s*-\s*/', '', $name);
        }

        return trim($name) !== '' ? trim($name) : $raw;
    }

    /** @return array{0:?float,1:?string} */
    private function money(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '' || preg_match('/not found|no proposal|folder empty|no price|separate/i', $raw)) {
            return [null, null];
        }
        $currency = preg_match('/\b(USD|CAD|EUR|GBP|AUD|JPY|CNY)\b/i', $raw, $m) ? strtoupper($m[1]) : null;
        $num = preg_replace('/[^0-9.]/', '', $raw);

        return [$num !== '' && (float) $num > 0 ? round((float) $num, 2) : null, $currency];
    }

    private function date(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '' || preg_match('/not found|folder empty|no proposal/i', $raw) || preg_match('/^[A-Za-z]+\s+\d{4}$/', $raw)) {
            return null; // blank, sentinel, or month-year only ("May 2026")
        }
        try {
            return Carbon::parse($raw)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private function short(?string $v): string
    {
        return mb_strimwidth(trim((string) $v), 0, 46, '…');
    }

    /**
     * Akin's CSV, transcribed. One row per line:
     *   folder | status | proposal amount | due date | submission date | raw title
     */
    private const DATA = <<<'DATA'
116|submitted|$128,886.00|July 28, 2025|July 28, 2025|140R8125Q02111 - TSC IIJA 8560 CNC MILL AND LATHE
148|submitted|$166,750 CAD|August 7, 2025|August 7, 2025|UOT202517342 - Vertical Computer Numerical Control (CNC) Machining System University of Toronto
378|submitted|$294,450 USD|June 10, 2026|May 26, 2026|Waterjet
375|submitted|$249,450 USD|June 5, 2026|May 29, 2026|CNC Ultrasonic Machining Center
391|submitted|$17,450 USD|June 15, 2026|June 14, 2026|Epilog Fusion CNC CO2 Laser or Equal
374|submitted|$84,550 USD|June 5, 2026|May 22, 2026|Press Brake
387|submitted|No proposal PDF found|June 8, 2026||CO2 Laser Cutter & Engraver
384|submitted|$284,450 USD|June 10, 2026|May 27, 2026|CNC Arc Plasma Cutting
373|submitted|$45,900.00|June 5, 2026|May 2026|2 Knee Type Milling Machine
363|submitted|$49,450.00|April 30, 2026|April 30, 2026|Dual Laser Engraver
340|submitted|$49,995.00|March 16, 2026|March 16, 2026|CO2 Laser Cutter & Engraver
321|submitted|$64,950.00|March 11, 2026|March 10, 2026|(1) X-Ray Luggage Scanner in Quito
312|submitted|$44,950.00|February 11, 2026|February 6, 2026|Milling Machine LADW
294|submitted|$94,850.00|February 9, 2026|February 4, 2026|CNC Router Cutting System
298|submitted|$554,990.00|January 31, 2026|January 30, 2026|Plasma Cutting Machine
272|submitted|$123,995.00|January 16, 2026|January 14, 2026|Large Roundness Machine
279|submitted|$65,940.00|January 14, 2026|December 30, 2025|1200W Laser Welder / CNC Cutter
215|submitted|$1,195,850.00|November 10, 2025|October 27, 2025|Vertical Milling Machine Dept of Army
156|submitted|$748,550.00|August 29, 2025|August 28, 2025|M6700425Q0051 - Horizontal Machining Center with Single Table
151|submitted|$39,550.00|August 11, 2025|August 9, 2025|1232SA25Q0548 - Instron 34SC-5 Materials Testing System, or comparable
183|submitted|$161,230.00|September 16, 2025|September 12, 2025|140R8125Q0339 - TSC IIJA 8560 CNC MILL AND LATHE
166|submitted|$148,980.00|September 12, 2025|September 4, 2025|W912K625QA018 - Waterjet Cutting Machine
370|submitted|No proposal PDF found|June 1, 2026||Laser Welding and Cutting Machine IAEA
381|submitted|$63,950.00|June 5, 2026|June 2, 2026|CNC Hawaii
377|submitted|$69,450.00|June 4, 2026|May 27, 2026|Tensile Testing Machine Installation
361|submitted|Not found (price in separate Bid Table)|May 15, 2026|May 8, 2026|Concrete Compression Machine
354|submitted|Folder empty - no files|April 8, 2026||Laser Engraing
118|submitted|Not found (docx only, no PDF)|September 4, 2026||Gas Chromotograph Mass Spectrometer - portal
248|submitted|Not found (price in separate vol)|December 17, 2025|December 15, 2025|CNC Tube Bending Machine Ontario
305|submitted|Not found (price in separate vol)|February 17, 2026|February 17, 2026|Mass Helium Leak Detector
295|submitted|$249,995.00|February 12, 2026|February 1, 2026|CNC Cutting System Rhode Island
010|submitted|Not found (price in separate vol)|February 10, 2026|February 3, 2026|Carbon Handheld Analyzer
110|submitted|Not found (price in separate vol)|January 16, 2026|January 15, 2026|ED XRF Spectrometer Chile IAEA
290|submitted|$72,980.00|January 23, 2026|January 21, 2026|CNC Plasma Cutting Systems
100|submitted|$316,750.00|December 30, 2025|December 29, 2025|Chromatography System with a Mass Spectrometer, Uni of Iowa
229|submitted|$98,250.00|November 19, 2025|November 19, 2025|CNC Lathe Machine Mississippi Gulf Coast Community College
224|submitted|Not found (no price in proposal)|November 6, 2025||Laser Welder
179|submitted|$26,481.00|September 15, 2025|September 10, 2025|CNC Machine for Its Fabrication Lab University of Oregon
150|lost||||District Shops Body Shop 130-Ton Hydraulic Press Brake Replacement
362|lost||||Laser Cleaner
351|lost||||Press Brake
361b|lost||||CNC Milling Machine AIR FORCE
357|lost||||LASER CORRISION CONTROL CLEANING MACHINE
126|lost||||Purchase of CNC Machines and Accessories - BBB Grant
337|lost||||UTM South Carolina
331|lost||||Ingham ISD - Plasma Cutting Table
314|lost||||CNC Mill and Lathe Machines, Korea
012|lost||||Laser Cleaner
311|lost||||RFP - ARSD CNC Router
283|lost||||Waterjet Cutting Machine - Machine Shop
274|lost||||CNC Plasma Cutting System Mountain Home Public Schools
289|lost||||CNC Milling Station SANDIA
287|lost||||Compact Enclosed Metal Cutting Fiber Laser
255|lost||||Hydraulic CNC Guillotine Shear
280|lost||||Purchase of Precision Press Metal Brake
271|lost||||Tensile Testing Machine with High-Temperature Testing System
259|lost||||Laser Engraving System CANADA
258|lost||||CNC Router Fox Valley Technical College
253|lost||||Hydraulic Press Brake Machine
251|lost||||CNC Lathe Machine Northeast Iowa Community College
250|lost||||CNC SmartShop 2 Pro Computer Controlled Cutting Flatbed
247|lost||||Fiber Laser Marker Alpena Community College
241|lost||||Laser Engraver AB, CA
246|lost||||CNC Fiber Laser Cutting Table Lake Area Technical Institute
240|lost||||Electrical Discharge Machine
214|lost||||Milling Machine Binghamton University
227|lost||||Compression and Tension Testing Equipment
238|lost||||Knee Milling Machine Texas A&M
164|lost||||140G0125Q0210 - METAL LATHE
219|lost||||Inductively Coupled Plasma Optical Emission Spectrometer (ICP-OES)
236|lost||||100KN Universal Testing Machine
223|lost||||5 Axis CNC Mill
197|lost||||100KN Universal Testing Machine
208|lost||||CNC Turning Machine
159|lost||||3-Axis CNC Mill
211|lost||||Compression Machine
182|lost||||W9132T25QA009 - Fiber Laser Cutter
202|lost||||United Nations Office CMM, CNC Machines, Plasma Cutter, Universal Lathe
206|lost||||One CNC Plasma Table, Support Materials and Training Construction
201|lost||||CNC Machine Compact Toolroom Del Mare College
192|lost||||CNC Router System
193|lost||||CNC Router System & Accessories
169|lost||||Spectrometer with other analytical equipment
191|lost||||W519TC25QA080 - CNC Lathe
181|lost||||75F40125Q00437 - 3-Axis Milling Machine
152|lost||||W51AA125Q0070 - CNC FIBER LASER CUTTING MACHINE Army Tobyhanna Depot PA
163|lost||||W912K625QA016 - CNC Milling and Lathe Machine
184|lost||||1232SA25Q0864 - Real-Time Quantitative Polymerase Chain Reaction qPCR Machine
157|lost||||140R8125Q0248 - LATHE
158|lost||||M6890925Q7913 - Fiber Laser Cutter USMC Camp Pendleton CA
160|lost||||W912JB25QA088 - CNC Mill Machine
175|lost||||W911S225U1893 - CNC Plasma Table
177|lost||||UTM South Carolina Uni
170|lost||||Nuclear Magnetic Resonance Spectrometer
DATA;
}
