<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessDhlWebhook;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * DHL "Shipment Tracking - Unified - Push" webhook receiver — DHL POSTs here with
 * subscription.validate / subscription.ready / subscription.push messages. DHL
 * doesn't sign notifications, so the endpoint is guarded by an unguessable secret
 * token in the path (the URL we register with DHL). Lives in routes/api.php
 * (no session/CSRF) and returns a fast 200 — the actual work runs on the queue,
 * because DHL retries anything that doesn't answer 200 within 5 seconds.
 */
class DhlWebhookController extends Controller
{
    public function handle(Request $request, string $token): JsonResponse
    {
        $expected = config('services.dhl.push.webhook_token');

        if (! is_string($expected) || $expected === '' || ! hash_equals($expected, $token)) {
            return response()->json(['message' => 'Invalid token.'], 401);
        }

        $payload = (array) $request->json()->all();

        // Ignore anything without a recognizable scope rather than churning the queue.
        if (! isset($payload['scope'])) {
            return response()->json(['status' => 'ignored'], 202);
        }

        ProcessDhlWebhook::dispatch($payload);

        return response()->json(['status' => 'accepted']);
    }
}
