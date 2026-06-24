<?php

namespace App\Console\Commands;

use App\Enums\OpportunitySource;
use App\Models\Opportunity;
use App\Services\BidSources\SamGov\SamLinkResolver;
use Illuminate\Console\Command;

/**
 * Back-fills the SAM.gov notice id (and canonical link) for SAM-sourced
 * opportunities that were imported without one, by resolving each solicitation
 * number against SAM.gov. After this runs, the opportunity's `sam_url` deep-links
 * straight to the notice's workspace page instead of a SAM search.
 */
class BackfillSamLinksCommand extends Command
{
    protected $signature = 'opportunities:backfill-sam-links
        {--limit=0 : Max opportunities to process (0 = all)}
        {--sleep=400 : Milliseconds to pause between SAM lookups}
        {--dry-run : Resolve only; do not persist}';

    protected $description = 'Resolve and store the SAM.gov notice id / link for SAM opportunities missing it';

    public function handle(SamLinkResolver $resolver): int
    {
        $query = Opportunity::query()
            ->where('source', OpportunitySource::SamGov->value)
            ->whereNull('external_id')
            ->whereNotNull('solicitation_number')
            ->where('solicitation_number', '!=', '')
            ->orderBy('id');

        $total = (clone $query)->count();
        $limit = (int) $this->option('limit');
        if ($limit > 0) {
            $query->limit($limit);
        }
        $sleepUs = max(0, (int) $this->option('sleep')) * 1000;
        $dry = (bool) $this->option('dry-run');

        $this->info("Backfilling SAM links for {$total} opportunit".($total === 1 ? 'y' : 'ies')
            ." missing a notice id".($dry ? ' (dry run)' : '').'.');

        $resolved = 0;
        $skipped = 0;
        $failed = 0;
        $i = 0;

        foreach ($query->cursor() as $opp) {
            $i++;
            try {
                $noticeId = $resolver->noticeIdForSolicitation((string) $opp->solicitation_number);
            } catch (\Throwable $e) {
                $failed++;
                $this->warn("  [{$i}] {$opp->solicitation_number} — error: {$e->getMessage()}");
                continue;
            }

            if (! $noticeId) {
                $skipped++;
                $this->line("  [{$i}] {$opp->solicitation_number} — no exact SAM match, left as search link");
                if ($sleepUs) {
                    usleep($sleepUs);
                }
                continue;
            }

            $url = 'https://sam.gov/workspace/contract/opp/'.$noticeId.'/view';
            if (! $dry) {
                try {
                    $opp->forceFill(['external_id' => $noticeId, 'source_url' => $url])->saveQuietly();
                } catch (\Throwable $e) {
                    $failed++;
                    $this->warn("  [{$i}] {$opp->solicitation_number} → {$noticeId} — save failed: {$e->getMessage()}");
                    continue;
                }
            }

            $resolved++;
            $this->line("  [{$i}] {$opp->solicitation_number} → {$noticeId}");
            if ($sleepUs) {
                usleep($sleepUs);
            }
        }

        $this->info("Done. Resolved {$resolved}, skipped {$skipped}, failed {$failed}.");

        return self::SUCCESS;
    }
}
