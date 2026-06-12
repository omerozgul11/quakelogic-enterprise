<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Delete in-app (database) notifications older than the retention window so the
 * notifications table — and every user's bell list — doesn't grow unbounded.
 * Read and unread alike are pruned; anything past the window is stale.
 */
class PruneOldNotificationsCommand extends Command
{
    protected $signature = 'notifications:prune {--days=30 : Delete notifications older than this many days}';

    protected $description = 'Delete in-app notifications older than the retention window (default 30 days).';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $cutoff = now()->subDays($days);

        $deleted = DatabaseNotification::where('created_at', '<', $cutoff)->delete();

        $this->info("Pruned {$deleted} notification(s) older than {$days} days.");

        return self::SUCCESS;
    }
}
