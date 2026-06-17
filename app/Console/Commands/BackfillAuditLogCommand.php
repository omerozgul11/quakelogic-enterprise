<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\ProposalSubmission;
use Illuminate\Console\Command;

/**
 * Seeds the (previously empty) audit log with historical proposal activity so the
 * admin Activity feed is useful from day one: a "created" event per proposal, and
 * an "updated" (status) event for every status change / submission already on
 * record. Idempotent — re-running replaces only its own backfilled rows (tagged
 * 'backfill'), never touching live audit entries.
 */
class BackfillAuditLogCommand extends Command
{
    protected $signature = 'audit:backfill';

    protected $description = 'Reconstruct historical proposal activity into the audit log (idempotent).';

    public function handle(): int
    {
        // Clear any prior backfill so re-runs don't duplicate; live rows are kept.
        AuditLog::whereJsonContains('tags', 'backfill')->delete();

        $created = 0;
        $status = 0;

        ProposalSubmission::withTrashed()->with('statusHistory')->chunk(200, function ($proposals) use (&$created, &$status) {
            foreach ($proposals as $p) {
                $this->write('created', $p, $p->created_by, $p->created_at, [], []);
                $created++;

                foreach ($p->statusHistory as $h) {
                    $this->write('updated', $p, $h->changed_by, $h->changed_at,
                        ['status' => $h->from_status],
                        ['status' => $h->to_status],
                    );
                    $status++;
                }
            }
        });

        $this->info("Backfilled audit log: {$created} 'created' + {$status} 'status change' event(s).");

        return self::SUCCESS;
    }

    private function write(string $event, ProposalSubmission $proposal, ?int $userId, $at, array $old, array $new): void
    {
        $log = new AuditLog([
            'organization_id' => $proposal->organization_id,
            'user_id' => $userId,
            'event' => $event,
            'auditable_type' => ProposalSubmission::class,
            'auditable_id' => $proposal->id,
            'old_values' => $old,
            'new_values' => $new,
            'tags' => ['backfill'],
        ]);
        // Preserve the historical timestamp (dirty values aren't overwritten on insert).
        $log->created_at = $at;
        $log->updated_at = $at;
        $log->save();
    }
}
