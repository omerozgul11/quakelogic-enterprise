<?php

namespace App\Modules\ExpenseTracker\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\ExpenseTracker\Jobs\SyncQuickBooksConnection;
use App\Modules\ExpenseTracker\Models\QuickBooksConnection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Intuit QuickBooks webhook receiver — the real-time QuickBooks→app path.
 * Intuit POSTs an event notification (signed with the app's webhook verifier
 * token) whenever entities change; we verify the signature, then queue an
 * immediate sync for each affected company. Stateless: lives in routes/api.php
 * (no session/CSRF), so it is reachable server-to-server from Intuit.
 */
class QuickBooksWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        $token = config('services.quickbooks.webhook_token');
        if (! $token || ! $this->signatureValid($request, $token)) {
            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        foreach ($request->input('eventNotifications', []) as $notification) {
            $realmId = $notification['realmId'] ?? null;
            if (! $realmId) {
                continue;
            }

            QuickBooksConnection::where('realm_id', $realmId)
                ->get()
                ->each(fn (QuickBooksConnection $c) => SyncQuickBooksConnection::dispatch($c->id));
        }

        // Intuit expects a fast 200; the actual sync runs on the queue.
        return response()->json(['status' => 'accepted']);
    }

    private function signatureValid(Request $request, string $token): bool
    {
        $header = $request->header('intuit-signature');
        if (! $header) {
            return false;
        }

        $expected = base64_encode(hash_hmac('sha256', $request->getContent(), $token, true));

        return hash_equals($expected, $header);
    }
}
