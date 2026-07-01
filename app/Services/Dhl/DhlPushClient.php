<?php

namespace App\Services\Dhl;

/**
 * DHL "Shipment Tracking - Unified - Push" subscription management. Bound to the
 * real client only when DHL_API_KEY is present; otherwise a fake drives dev/tests
 * so the subscription flow is exercisable without hitting DHL (see
 * AppServiceProvider). Inbound push notifications land on DhlWebhookController.
 */
interface DhlPushClient
{
    /** Subscribe to updates for a single tracking number (immediate activation). */
    public function createShipmentSubscription(string $trackingNumber, string $callbackUrl): DhlSubscriptionResult;

    /** Subscribe to every shipment on a DHL account (needs DHL business approval). */
    public function createAccountSubscription(string $accountNumber, string $callbackUrl): DhlSubscriptionResult;

    /** Confirm ownership of the webhook using the secret from the validate event. */
    public function activate(string $subscriptionId, string $secret): void;

    /** Remove a subscription so DHL stops pushing updates. */
    public function delete(string $subscriptionId): void;

    /** @return DhlSubscriptionResult[] */
    public function list(): array;
}
