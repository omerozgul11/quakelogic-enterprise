<?php

namespace App\Console\Commands;

use App\Enums\OpportunitySource;
use App\Models\Opportunity;
use App\Services\BidSources\OpportunityDocumentService;
use Illuminate\Console\Command;

/**
 * Pulls missing solicitation documents for opportunities that were imported
 * without them — SAM full-text imports and BidPrime leads land with no
 * resourceLinks. For each doc-less opportunity, OpportunityDocumentService::ensure()
 * resolves a SAM.gov notice id (from the SAM id, an embedded SAM URL, or the
 * solicitation number) and merges the official record's resourceLinks into
 * raw_source_data, so the documents become downloadable in the app.
 *
 * Runs nightly after the source syncs; also safe to run by hand to back-fill
 * the whole history.
 */
class BackfillSamDocumentsCommand extends Command
{
    protected $signature = 'opportunities:backfill-sam-documents
        {--source=all : Limit to a source: sam_gov, bidprime, or all}
        {--organization= : Only this organization id}
        {--limit=0 : Max opportunities to process (0 = all)}
        {--due-within-days=0 : Only opportunities closing within this many days (0 = no due-date filter)}
        {--sleep=400 : Milliseconds to pause between SAM lookups}
        {--force : Re-probe even notices confirmed empty in the last week (default: skip them so the run converges)}
        {--dry-run : Report what would be attempted; do not fetch or persist}';

    protected $description = 'Pull missing solicitation documents (SAM resourceLinks) for opportunities that have none';

    public function handle(OpportunityDocumentService $docs): int
    {
        $query = Opportunity::query()->orderByDesc('id')
            // Only opportunities that have no documents yet — so --limit is
            // meaningful and the nightly run skips the thousands already pulled.
            ->whereRaw("(JSON_EXTRACT(raw_source_data, '$.resourceLinks') IS NULL"
                ." OR JSON_LENGTH(JSON_EXTRACT(raw_source_data, '$.resourceLinks')) = 0)");

        $source = (string) $this->option('source');
        if ($source !== 'all') {
            $query->where('source', $source);
        } else {
            $query->whereIn('source', [OpportunitySource::SamGov->value, OpportunitySource::BidPrime->value]);
        }
        if ($org = $this->option('organization')) {
            $query->where('organization_id', (int) $org);
        }

        // Focus the daily run on opportunities actually closing soon: those with a
        // due date from today through the next N days. This is a rolling window —
        // as new opportunities approach their deadline they enter it automatically,
        // and anything due further out (or already past) is left alone.
        $dueWithin = (int) $this->option('due-within-days');
        if ($dueWithin > 0) {
            $query->whereNotNull('due_date')
                ->whereDate('due_date', '>=', now()->toDateString())
                ->whereDate('due_date', '<=', now()->addDays($dueWithin)->toDateString())
                ->reorder('due_date'); // soonest-closing first
        }

        $total = (clone $query)->count();
        $limit = (int) $this->option('limit');
        if ($limit > 0) {
            $query->limit($limit);
        }
        $sleepUs = max(0, (int) $this->option('sleep')) * 1000;
        $dry = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        $scope = $dueWithin > 0 ? " closing within {$dueWithin} days" : '';
        $this->info("Scanning {$total} opportunit".($total === 1 ? 'y' : 'ies')
            ."{$scope} for missing documents".($dry ? ' (dry run)' : '').'.');

        $pulled = 0;
        $already = 0;
        $none = 0;
        $skipped = 0;
        $throttled = 0;
        $consecutiveThrottles = 0;
        $i = 0;

        // Once SAM's daily quota is exhausted every further lookup is throttled;
        // bail after a short run of throttles rather than burning the rest of the
        // batch on 429s. The nightly schedule picks up where this left off.
        $maxConsecutiveThrottles = 5;

        foreach ($query->cursor() as $opp) {
            $i++;

            if (count($docs->list($opp)) > 0) {
                $already++;
                continue;
            }

            if ($dry) {
                $this->line("  [{$i}] #{$opp->id} {$opp->title} — would attempt to resolve documents");
                continue;
            }

            try {
                $status = $docs->ensure($opp, force: $force);
            } catch (\Throwable $e) {
                $this->warn("  [{$i}] #{$opp->id} — error: {$e->getMessage()}");
                continue;
            }

            if ($status === 'throttled') {
                $throttled++;
                $consecutiveThrottles++;
                if ($consecutiveThrottles >= $maxConsecutiveThrottles) {
                    $this->warn("  SAM.gov quota reached (throttled {$consecutiveThrottles}× in a row) — stopping."
                        .' Re-run after the daily reset; the schedule resumes automatically.');
                    break;
                }
                continue;
            }
            $consecutiveThrottles = 0;

            if ($status === 'pulled') {
                $pulled++;
                $count = count($docs->list($opp));
                $this->line("  [{$i}] #{$opp->id} {$opp->title} — pulled {$count} document(s)");
            } elseif ($status === 'none') {
                $none++; // freshly confirmed empty
            } else {
                // none_cached / pending / unresolved — no fresh SAM call, nothing to pace.
                $skipped++;
                continue;
            }

            // Only pace after a real SAM lookup; cache-served rows above don't reach here.
            if ($sleepUs) {
                usleep($sleepUs);
            }
        }

        $this->info("Done. Pulled docs for {$pulled}, already had {$already}, none found {$none}"
            .($skipped ? ", skipped {$skipped} (already checked/pending)" : '')
            .($throttled ? ", throttled {$throttled}" : '').'.');

        return self::SUCCESS;
    }
}
