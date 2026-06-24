<?php

namespace App\Modules\ExpenseTracker\Jobs;

use App\Modules\ExpenseTracker\Models\QuickBooksConnection;
use App\Modules\ExpenseTracker\Services\QuickBooksSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Runs a full sync for one connection off the request path. Dispatched by the
 * QuickBooks webhook so a change in QuickBooks is reflected in the app within
 * seconds (the webhook itself returns 200 immediately).
 */
class SyncQuickBooksConnection implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $connectionId) {}

    public function handle(QuickBooksSyncService $sync): void
    {
        $connection = QuickBooksConnection::find($this->connectionId);
        if ($connection) {
            $sync->syncOrganization($connection);
        }
    }
}
