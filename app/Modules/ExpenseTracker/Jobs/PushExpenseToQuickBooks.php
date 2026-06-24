<?php

namespace App\Modules\ExpenseTracker\Jobs;

use App\Modules\ExpenseTracker\Models\Expense;
use App\Modules\ExpenseTracker\Models\QuickBooksConnection;
use App\Modules\ExpenseTracker\Services\QuickBooksSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Pushes one expense to QuickBooks the moment it is approved or edited in the
 * app. Dispatched by ExpenseObserver; processed by the running queue worker so
 * the write reaches QuickBooks within seconds.
 */
class PushExpenseToQuickBooks implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $expenseId) {}

    public function handle(QuickBooksSyncService $sync): void
    {
        $expense = Expense::find($this->expenseId);
        if (! $expense) {
            return;
        }

        $connection = QuickBooksConnection::where('organization_id', $expense->organization_id)
            ->where('push_enabled', true)->first();
        if (! $connection) {
            return;
        }

        $sync->ensureFreshToken($connection);
        $sync->pushExpense($connection, $expense);
    }
}
