<?php

namespace App\Modules\ExpenseTracker\Observers;

use App\Modules\ExpenseTracker\Jobs\PushExpenseToQuickBooks;
use App\Modules\ExpenseTracker\Models\Expense;
use App\Modules\ExpenseTracker\Models\QuickBooksConnection;
use App\Modules\ExpenseTracker\Services\QuickBooksSyncService;

/**
 * Real-time outbound sync: the instant a manual, approved expense is created or
 * its money-relevant fields change, queue a push to QuickBooks. Writes made by
 * the sync itself (guarded by QuickBooksSyncService::$isSyncing) and
 * QuickBooks-sourced rows are ignored so nothing echoes in a loop.
 */
class ExpenseObserver
{
    public function saved(Expense $expense): void
    {
        if (QuickBooksSyncService::$isSyncing) {
            return; // change originated from a sync — don't bounce it back out
        }
        if ($expense->source !== 'manual' || ! $expense->status->countsAsSpend()) {
            return; // only locally-created, approved spend is pushed
        }

        $relevant = $expense->wasRecentlyCreated || $expense->wasChanged([
            'status', 'amount', 'vendor', 'description', 'expense_date', 'expense_category_id', 'currency',
        ]);
        if (! $relevant) {
            return;
        }

        $hasPushTarget = QuickBooksConnection::where('organization_id', $expense->organization_id)
            ->where('push_enabled', true)->exists();
        if ($hasPushTarget) {
            PushExpenseToQuickBooks::dispatch($expense->id);
        }
    }
}
