<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\DhlPushSubscription;
use App\Services\Dhl\DhlPushClient;
use App\Services\Dhl\DhlPushException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Manages DHL tracking push subscriptions from the Carriers page: subscribe a
 * tracking number (or a whole account) so DHL pushes live updates to our webhook,
 * and unsubscribe. Requires DHL_API_KEY + a webhook token to be configured;
 * inbound updates are handled by DhlWebhookController → DhlPushIngestService.
 */
class DhlSubscriptionController extends Controller
{
    public function subscribe(Request $request, DhlPushClient $push): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['nullable', 'in:shipment,account'],
            'tracking_number' => ['required_without:account_number', 'nullable', 'string', 'max:100'],
            'account_number' => ['nullable', 'string', 'max:100'],
        ]);

        if (! $this->configured()) {
            return back()->with('warning', 'Add DHL_API_KEY and DHL_PUSH_WEBHOOK_TOKEN to the app env before connecting DHL push updates.');
        }

        $callbackUrl = $this->callbackUrl();
        $type = ($data['type'] ?? null) === DhlPushSubscription::TYPE_ACCOUNT || ! empty($data['account_number'])
            ? DhlPushSubscription::TYPE_ACCOUNT
            : DhlPushSubscription::TYPE_SHIPMENT;

        try {
            $result = $type === DhlPushSubscription::TYPE_ACCOUNT
                ? $push->createAccountSubscription((string) $data['account_number'], $callbackUrl)
                : $push->createShipmentSubscription((string) $data['tracking_number'], $callbackUrl);
        } catch (DhlPushException $e) {
            report($e);

            return back()->with('error', 'DHL rejected the subscription request. Check the API key and try again.');
        }

        DhlPushSubscription::create([
            'organization_id' => $request->user()->organization_id,
            'subscription_id' => $result->id !== '' ? $result->id : null,
            'type' => $type,
            'tracking_number' => $data['tracking_number'] ?? null,
            'account_number' => $data['account_number'] ?? null,
            'status' => DhlPushSubscription::STATUS_PENDING,
            'callback_url' => $callbackUrl,
            'created_by' => $request->user()->id,
        ]);

        $label = $type === DhlPushSubscription::TYPE_ACCOUNT
            ? "DHL account {$data['account_number']}"
            : "DHL tracking {$data['tracking_number']}";

        return back()->with('success', "Subscribed {$label}. DHL will confirm the webhook, then push live updates.");
    }

    public function destroy(Request $request, DhlPushClient $push): RedirectResponse
    {
        $data = $request->validate([
            'ulid' => ['required', 'string'],
        ]);

        $subscription = DhlPushSubscription::forOrganization($request->user()->organization_id)
            ->where('ulid', $data['ulid'])
            ->firstOrFail();

        if ($subscription->subscription_id && $this->configured()) {
            try {
                $push->delete($subscription->subscription_id);
            } catch (DhlPushException $e) {
                report($e); // best-effort — remove our record regardless
            }
        }

        $subscription->update(['status' => DhlPushSubscription::STATUS_REMOVED]);
        $subscription->delete();

        return back()->with('success', 'DHL subscription removed.');
    }

    private function configured(): bool
    {
        return (bool) config('services.dhl.api_key')
            && (bool) config('services.dhl.push.webhook_token');
    }

    private function callbackUrl(): string
    {
        $configured = config('services.dhl.push.webhook_url');
        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return url('/api/dhl/webhook/'.config('services.dhl.push.webhook_token'));
    }
}
