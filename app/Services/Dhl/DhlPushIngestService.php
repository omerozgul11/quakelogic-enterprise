<?php

namespace App\Services\Dhl;

use App\Enums\Carrier;
use App\Enums\MailingStatus;
use App\Models\DhlPushSubscription;
use App\Models\ProposalMailing;
use App\Models\User;
use App\Services\Mailings\MailingTrackingService;
use App\Services\Tracking\TrackingResult;
use Throwable;

/**
 * Consumes DHL tracking push notifications (webhook). DHL sends three message
 * scopes to our endpoint:
 *   - subscription.validate → carries a secret; we confirm ownership by activating
 *   - subscription.ready    → the subscription is now live
 *   - subscription.push     → the latest status of one or more shipments
 *
 * A push is attributed to an org via the subscription it came from (the `self`
 * URL), so account-level pushes land in the right tenant. Real DHL data only —
 * we never invent a shipment we can't attribute to an org.
 */
class DhlPushIngestService
{
    public function __construct(
        private readonly MailingTrackingService $tracking,
        private readonly DhlShipmentMapper $mapper,
    ) {}

    /** @param array<string,mixed> $payload */
    public function handle(array $payload): void
    {
        match ((string) ($payload['scope'] ?? '')) {
            'subscription.validate' => $this->validate($payload),
            'subscription.ready' => $this->setStatus($payload, DhlPushSubscription::STATUS_READY),
            'subscription.push' => $this->push($payload),
            'subscription.deleted', 'subscription.cancelled', 'subscription.expired' => $this->setStatus($payload, DhlPushSubscription::STATUS_REMOVED),
            default => null,
        };
    }

    /** @param array<string,mixed> $payload */
    private function validate(array $payload): void
    {
        $id = $this->subscriptionId($payload);
        $secret = (string) ($payload['secret'] ?? '');
        if ($id === null || $secret === '') {
            return;
        }

        $subscription = DhlPushSubscription::where('subscription_id', $id)->first();
        $subscription?->update(['secret' => $secret, 'status' => DhlPushSubscription::STATUS_VALIDATING]);

        // Confirm we own the webhook. DHL then follows up with a ready event.
        try {
            app(DhlPushClient::class)->activate($id, $secret);
        } catch (Throwable $e) {
            $subscription?->update(['status' => DhlPushSubscription::STATUS_FAILED]);
            report($e);
        }
    }

    /** @param array<string,mixed> $payload */
    private function setStatus(array $payload, string $status): void
    {
        $id = $this->subscriptionId($payload);
        if ($id !== null) {
            DhlPushSubscription::where('subscription_id', $id)
                ->update(['status' => $status, 'last_event_at' => now()]);
        }
    }

    /** @param array<string,mixed> $payload */
    private function push(array $payload): void
    {
        $id = $this->subscriptionId($payload);
        $subscription = $id !== null ? DhlPushSubscription::where('subscription_id', $id)->first() : null;
        $subscription?->update(['last_event_at' => now(), 'status' => DhlPushSubscription::STATUS_READY]);

        foreach (($payload['shipments'] ?? []) as $shipment) {
            if (! is_array($shipment)) {
                continue;
            }

            $trackingNumber = trim((string) ($shipment['id'] ?? ''));
            if ($trackingNumber === '') {
                continue;
            }

            $this->applyToMailings($trackingNumber, $this->mapper->toResult($shipment), $subscription);
        }
    }

    private function applyToMailings(string $trackingNumber, TrackingResult $result, ?DhlPushSubscription $subscription): void
    {
        $query = ProposalMailing::query()
            ->where('carrier', Carrier::Dhl->value)
            ->where('ups_tracking_number', $trackingNumber);

        if ($subscription?->organization_id) {
            $query->where('organization_id', $subscription->organization_id);
        }

        $mailings = $query->get();

        if ($mailings->isEmpty()) {
            // Only auto-create for an account subscription, where DHL pushes every
            // shipment on the account and we can attribute it to the org. A row is
            // real DHL data — never fabricated. Skip anything we can't attribute.
            $created = $this->maybeCreate($trackingNumber, $subscription);
            if ($created === null) {
                return;
            }
            $mailings = collect([$created]);
        }

        foreach ($mailings as $mailing) {
            $this->tracking->apply($mailing, $result, notify: true);
        }
    }

    private function maybeCreate(string $trackingNumber, ?DhlPushSubscription $subscription): ?ProposalMailing
    {
        if ($subscription === null
            || $subscription->type !== DhlPushSubscription::TYPE_ACCOUNT
            || ! $subscription->organization_id) {
            return null;
        }

        $creatorId = $subscription->created_by ?? $this->systemUserId($subscription->organization_id);
        if (! $creatorId) {
            return null; // created_by is required — don't persist an orphan row
        }

        $mailing = new ProposalMailing(['ups_tracking_number' => $trackingNumber]);
        $mailing->organization_id = $subscription->organization_id;
        $mailing->created_by = $creatorId;
        $mailing->carrier = Carrier::Dhl->value;
        $mailing->status = MailingStatus::LabelCreated;
        $mailing->auto_track = false; // push-driven, not polled
        $mailing->save();

        return $mailing;
    }

    private function systemUserId(int $orgId): ?int
    {
        return User::where('organization_id', $orgId)->role('Super Admin')->value('id')
            ?? User::where('organization_id', $orgId)->value('id');
    }

    /** The subscription UUID is the last path segment of the event's `self` URL. */
    private function subscriptionId(array $payload): ?string
    {
        $self = (string) ($payload['self'] ?? '');
        if ($self === '') {
            return null;
        }

        $path = (string) (parse_url($self, PHP_URL_PATH) ?: $self);
        $segments = array_values(array_filter(explode('/', $path), fn ($s) => trim($s) !== ''));

        return $segments !== [] ? end($segments) : null;
    }
}
