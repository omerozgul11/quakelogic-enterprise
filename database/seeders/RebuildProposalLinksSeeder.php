<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Safely rebuild cross-platform FK links lost in the 2026-06-17 wipe, using only
 * EXACT matches in the recovered data (no fuzzy guessing). Idempotent and
 * non-destructive: only fills columns that are currently NULL, so it can be
 * re-run and never overwrites a correct value.
 *
 *   - opportunities.agency_id  <- exact agency_name == agencies.name
 *   - proposals.opportunity_id <- exact solicitation_number match
 *   - proposals.agency_id      <- inherited from the matched opportunity
 *
 * Not rebuilt (no reliable signal survived): proposals/opportunities -> company,
 * and shipments (proposal_mailings has 0 rows).
 */
class RebuildProposalLinksSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Opportunities -> agency by exact agency name.
        $oppAgency = 0;
        foreach (DB::table('agencies')->pluck('id', 'name') as $name => $agencyId) {
            if ($name === null || $name === '') {
                continue;
            }
            $oppAgency += DB::table('opportunities')
                ->whereNull('agency_id')
                ->where('agency_name', $name)
                ->update(['agency_id' => $agencyId]);
        }
        $this->command->info("Opportunities linked to an agency: {$oppAgency}");

        // 2. Build solicitation_number -> opportunity map (latest id wins on dupes).
        $oppBySol = [];
        DB::table('opportunities')
            ->whereNotNull('solicitation_number')->where('solicitation_number', '!=', '')
            ->orderBy('id')
            ->get(['id', 'solicitation_number', 'agency_id'])
            ->each(function ($o) use (&$oppBySol) {
                $oppBySol[$o->solicitation_number] = $o;
            });

        // 3. Proposals -> opportunity (and inherit its agency) by exact solicitation #.
        $linkedOpp = 0;
        $linkedAgency = 0;
        DB::table('proposal_submissions')
            ->whereNull('opportunity_id')
            ->whereNotNull('solicitation_number')->where('solicitation_number', '!=', '')
            ->get(['id', 'solicitation_number', 'agency_id'])
            ->each(function ($p) use ($oppBySol, &$linkedOpp, &$linkedAgency) {
                $o = $oppBySol[$p->solicitation_number] ?? null;
                if (! $o) {
                    return;
                }
                $update = ['opportunity_id' => $o->id];
                if (! $p->agency_id && $o->agency_id) {
                    $update['agency_id'] = $o->agency_id;
                    $linkedAgency++;
                }
                DB::table('proposal_submissions')->where('id', $p->id)->update($update);
                $linkedOpp++;
            });

        $this->command->info("Proposals linked to an opportunity: {$linkedOpp}");
        $this->command->info("Proposals linked to an agency (via opportunity): {$linkedAgency}");
    }
}
