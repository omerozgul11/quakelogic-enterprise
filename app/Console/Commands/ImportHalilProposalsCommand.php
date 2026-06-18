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
 * Restore Halil's proposal book from his CSV. Halil's proposals (simulators,
 * robotics, temperature monitoring) almost all already exist — his simulators
 * under Halil, the robotics/temperature ones under "Admin" — so this mostly
 * enriches existing rows (value, dates, status) and reassigns the Admin-owned
 * ones to Halil. Each row carries a curated target: an existing proposal id to
 * update, or "new" to create. Idempotent via a "[halil-folder:NNN]" marker.
 * --dry-run previews; --force skips the prompt.
 */
class ImportHalilProposalsCommand extends Command
{
    protected $signature = 'proposals:import-halil
        {--dry-run : Show what would change without writing}
        {--force : Skip the production-DB confirmation prompt}';

    protected $description = "Restore Halil's proposal book from his CSV (update matched existing rows, create the rest).";

    public function handle(): int
    {
        $halil = User::where('email', 'halil@quakelogic.net')->first() ?? User::where('name', 'Halil')->first();
        if (! $halil) {
            $this->error('Could not find Halil (halil@quakelogic.net). Aborting.');

            return self::FAILURE;
        }

        $dry = (bool) $this->option('dry-run');
        $db = DB::connection()->getDatabaseName();
        $this->warn(($dry ? '[dry-run] ' : '') . "Target database: {$db} · Halil id: {$halil->id} · org: {$halil->organization_id}");

        if (! $dry && ! $this->option('force') && ! $this->confirm("Apply Halil's CSV (owner/status/value/dates) in [{$db}]?", false)) {
            $this->line('Aborted.');

            return self::SUCCESS;
        }

        $seq = $this->nextSequence($halil->organization_id);
        $year = now()->year;
        $updated = 0;
        $created = 0;
        $fromAdmin = 0;

        foreach ($this->rows() as $row) {
            $fields = $this->fields($row, $halil);
            $label = Str::limit($row['title'], 46);

            if ($row['target'] === 'new') {
                $existing = ProposalSubmission::where('notes', 'like', '%[halil-folder:' . $row['folder'] . ']%')->first();
                if ($existing) {
                    $this->line("  refresh ##{$existing->id} (folder {$row['folder']}) \"{$label}\"");
                    if (! $dry) {
                        $existing->forceFill($fields)->save();
                    }
                    $updated++;

                    continue;
                }
                $this->line("  create folder {$row['folder']} \"{$label}\" [{$row['status']}]" . $this->money($fields));
                if (! $dry) {
                    $proposal = ProposalSubmission::create(array_merge($fields, [
                        'organization_id' => $halil->organization_id,
                        'created_by' => $halil->id,
                        'proposal_manager_id' => $halil->id,
                        'proposal_number' => sprintf('QL-%d-%04d', $year, $seq++),
                    ]));
                    $proposal->statusHistory()->create([
                        'changed_by' => $halil->id,
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
            $note = (int) $proposal->owner_id === 1 ? '  (from Admin)' : ((int) $proposal->owner_id !== (int) $halil->id ? "  (from owner #{$proposal->owner_id})" : '');
            if ((int) $proposal->owner_id === 1) {
                $fromAdmin++;
            }
            $this->line("  update #{$proposal->id} \"{$label}\" [{$row['status']}]" . $this->money($fields) . $note);
            if (! $dry) {
                $proposal->forceFill($fields)->save();
            }
            $updated++;
        }

        $this->line('');
        $this->info(($dry ? '[dry-run] ' : '') . "Done. Updated {$updated} (incl. {$fromAdmin} reassigned from Admin) · created {$created}.");

        return self::SUCCESS;
    }

    private function fields(array $row, User $halil): array
    {
        return [
            'owner_id' => $halil->id,
            'status' => ProposalStatus::from($row['status']),
            'project_name' => $row['title'],
            'proposal_value' => $row['value'] !== '' ? (float) $row['value'] : null,
            'currency' => Currency::normalize($row['currency'] !== '' ? $row['currency'] : Currency::DEFAULT),
            'due_date' => $row['due'] !== '' ? $row['due'] : null,
            'submission_date' => $row['submission'] !== '' ? $row['submission'] : null,
            'notes' => '[halil-folder:' . $row['folder'] . ']',
        ];
    }

    private function money(array $fields): string
    {
        return $fields['proposal_value']
            ? " [{$fields['currency']} " . number_format((float) $fields['proposal_value']) . ']'
            : '';
    }

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
     * Halil's CSV, curated. One row per line:
     *   folder | target(existing id or "new") | status | value | currency | due(Y-m-d) | submission(Y-m-d) | title
     * Dates normalised from the CSV's mixed MM.DD.YY / DD.MM.YY using the submission month.
     * Lost rows carry no amount/dates. (QL109 appears twice in the source — the lost
     * KCTCS row is keyed QL109b to keep folder markers distinct.)
     */
    private const DATA = <<<'DATA'
QL162|25|submitted|69495|CAD|2026-06-10|2026-06-10|Dental Simulation Units
QL163|33|submitted|89450|USD|2026-07-03|2026-06-08|Request for Quotes for Excavator Simulators
QL164|32|submitted|99500|USD|2026-06-17|2026-06-17|Supply, Delivery & Support of One (1) Mini Tactical Robot System
QL161|114|submitted|126000|USD|2026-06-16|2026-06-16|Bid No 2526-20 Full Swing Golf Simulator
QL160|34|submitted|94500|USD|2026-06-09|2026-06-09|Welding Simulators
QL109|52|submitted|189990|USD|2026-02-02|2026-02-02|Patient Simulator
QL157|36|submitted||USD|2026-06-17|2026-06-17|IFB 50-2526011 Training Equipment and Simulators
QL155|37|submitted|79500|USD|2026-05-14|2026-05-01|Hellman Family Wellness Center Golf Simulator Purchase and Installation
QL151|39|submitted|159900|USD|2026-04-17|2026-04-17|High-Fidelity Medical Simulation Manikin Package
QL152|38|submitted|262500|USD|2026-04-23|2026-04-23|Simulation Mannikins
QL142|40|submitted|69900|USD|2026-03-14|2026-03-14|Articulated Robotic Arm
QL140|43|submitted|||2026-03-05||RFQ - Purchase of Robotics
QL138|42|submitted|||2026-02-19|2026-02-19|Labor & Delivery Patient Simulator GIMC
QL111|50|submitted|38990|USD|2026-05-01|2026-01-05|Drivers Education Simulator
QL121|45|submitted|1321480|CAD|2025-10-21|2025-10-21|Airside Driving Simulator
QL110|51|submitted|258650|USD|2025-10-06|2025-10-06|Temperature Monitoring Service for Lab Freezers
QL115|47|submitted|156890|USD|2025-12-09|2025-12-09|Commercial Driver's License Training Simulator
QL122|44|submitted|14750|USD|2026-02-27|2026-01-27|Automotive Driving Simulator
QL113|49|submitted|6999|USD|2025-11-19|2025-11-19|Remote Temperature Monitoring Services
QL116|46|submitted|129990|USD|2026-01-27|2026-01-27|Remote Temperature Monitoring System
QL114|48|submitted|174995|USD|2025-11-03|2025-10-27|Purchase of Two LXK49C Full Cab Driving Simulators
QL135|41|submitted|||2026-02-27||RFQ - Purchase of Robotics
201|new|lost|||||SY 06-25-1067 - High-Speed Real-Time Sampling Oscilloscope
QL156|53|lost|||||Childbirth Simulators and Related Items
QL154|54|lost|||||Currie Golf Simualtor Re-Bid
QL153|55|lost|||||IFB 50-2526011 Training Equipment and Simulators - Sourcing Event
QL149|56|lost|||||Robotic Arm
QL147|57|lost|||||ITB 4639 - Currie Golf Course Simulator
QL145|59|lost|||||Advanced Nursing Simulation Manikin
QL144|60|lost|||||Golf Simulators
QL146|58|lost|||||Advanced Robots
QL141|62|lost|||||Childbirth & Patient Simulators
QL143|61|lost|||||RFP - Birthing Simulator
QL139|69|lost|||||Complete Commercial Golf Simulator System and Installation
QL136|71|lost|||||IFB MTPD Small Platform EOD Reconnaissance Robots
QL137|70|lost|||||Terrace Bay Municipal Golf Simulator Project
QL133|73|lost|||||Hybrid and Battery Electric Vehicles Trainer
QL132|74|lost|||||Simlog Tabletop Forklift Personal Simulator
QL120|85|lost|||||Golf Simulator
QL134|72|lost|||||Temperature Tracking, Monitoring, and Probes
QL109b|90|lost|||||KCTCS EV Tranining
QL130|76|lost|||||Simlog Forklift Personal Simulator With Operator Chair
QL131|75|lost|||||Full Motion Flight Simulator
QL112|89|lost|||||Welding Simulator
QL117|88|lost|||||Tabletop Forklif Simulator
QL124|83|lost|||||20 Virtual Reality Welding Simulators
QL129|77|lost|||||Transit Bus STS Simulator
QL127|79|lost|||||CDL Driving Simulator
QL118|87|lost|||||Driving Simulator
QL119|86|lost|||||Furnish & Delivery of Welding Simulator
QL125|82|lost|||||Welding Augmented Reality Simulator
QL123|84|lost|||||Bus Driving Simulator
QL128|78|lost|||||Flight Simulators
QL126|81|lost|||||Delivery and installation of a temperature and humidity control monitoring system for an existing walk-in cooler
QL104|93|lost|||||Supply, Deliver, and Installation of Dental Patient Simulators
QL105|92|lost|||||Multi-Robot System
QL108|91|lost|||||Golf Simulator
QL103|94|lost|||||Web-Based Temperature Monitoring System & Services
DATA;
}
