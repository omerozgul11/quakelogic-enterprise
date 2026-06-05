<?php

namespace App\Jobs;

use App\Models\Organization;
use App\Services\BidSources\BidPrime\BidPrimeImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncBidPrimeOpportunitiesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;
    public int $tries = 2;

    public function __construct(
        private readonly int $organizationId,
        private readonly array $filters = []
    ) {
        $this->onQueue('imports');
    }

    public function handle(BidPrimeImportService $service): void
    {
        $organization = Organization::findOrFail($this->organizationId);
        $import = $service->import($organization, $this->filters);

        Log::info('BidPrime sync completed', [
            'organization_id' => $this->organizationId,
            'created' => $import->total_created,
            'updated' => $import->total_updated,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SyncBidPrimeOpportunitiesJob failed', [
            'organization_id' => $this->organizationId,
            'error' => $exception->getMessage(),
        ]);
    }
}
