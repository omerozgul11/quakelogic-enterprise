<?php

namespace App\Jobs;

use App\Services\Dhl\DhlPushIngestService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Processes a DHL tracking push notification off the request thread, so the
 * webhook can return its fast 200 (DHL requires a 200 within 5s or it retries).
 *
 * @see \App\Http\Controllers\Webhook\DhlWebhookController
 */
class ProcessDhlWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @param array<string,mixed> $payload */
    public function __construct(public readonly array $payload) {}

    public function handle(DhlPushIngestService $ingest): void
    {
        $ingest->handle($this->payload);
    }
}
