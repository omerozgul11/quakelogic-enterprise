<?php

namespace App\Services\Mailings;

use App\Enums\DeliveryRisk;
use App\Enums\MailingStatus;
use App\Models\MailingTrackingEvent;
use App\Models\ProposalMailing;
use App\Models\User;
use App\Notifications\MailingStatusChanged;
use App\Services\Tracking\TrackingClientFactory;
use App\Services\Tracking\TrackingResult;
use Illuminate\Support\Facades\DB;

/**
 * Applies carrier tracking data onto a mailing: upserts the event timeline,
 * advances the status, records delivery + proof, caches the on-time result vs
 * the deadline, and fires in-app alerts on notable status changes (and once when
 * a shipment becomes at-risk). Carrier-agnostic via TrackingClientFactory.
 */
class MailingTrackingService
{
    public function __construct(private readonly TrackingClientFactory $carriers) {}

    /**
     * Poll the mailing's carrier and persist the result. $notify=false suppresses
     * alerts for the initial population right after a mailing is created.
     */
    public function refresh(ProposalMailing $mailing, bool $notify = true): ProposalMailing
    {
        $result = $this->carriers->for($mailing->carrier)->track($mailing->ups_tracking_number);

        return $this->apply($mailing, $result, $notify);
    }

    public function apply(ProposalMailing $mailing, TrackingResult $result, bool $notify = true): ProposalMailing
    {
        $previousStatus = $mailing->getRawOriginal('status');

        $fresh = DB::transaction(function () use ($mailing, $result) {
            foreach ($result->events as $event) {
                MailingTrackingEvent::firstOrCreate(
                    [
                        'proposal_mailing_id' => $mailing->id,
                        'code' => $event->code,
                        'occurred_at' => $event->occurredAt,
                    ],
                    [
                        'description' => $event->description,
                        'location' => $event->location,
                    ],
                );
            }

            $mailing->status = $result->status;
            $mailing->scheduled_delivery = $result->scheduledDelivery;

            if ($result->status === MailingStatus::Delivered) {
                $mailing->delivered_at = $result->deliveredAt;
                $mailing->received_by = $result->receivedBy;
                $mailing->proof_url = $result->proofUrl ?? $mailing->proof_url;
                $mailing->on_time = $this->wasOnTime($mailing);
            }

            $mailing->save();

            return $mailing->fresh('trackingEvents');
        });

        if ($notify && $result->status->value !== $previousStatus) {
            $this->alert($fresh, $result->status);
        }

        return $fresh;
    }

    /**
     * Alert (once) when a still-moving shipment is projected to arrive after its
     * deadline. Deduped against the recipient's existing notifications so the
     * 30-minute poll doesn't repeat it.
     */
    public function alertAtRiskIfNeeded(ProposalMailing $mailing): void
    {
        if ($mailing->status->isTerminal() || $mailing->risk() !== DeliveryRisk::AtRisk) {
            return;
        }

        $title = 'At risk of missing deadline';
        $deadline = $mailing->deadline ? $mailing->deadline->toFormattedDateString() : 'the deadline';
        $message = $this->who($mailing)." is projected to arrive after {$deadline}.";
        $url = '/shipments/mailings/'.$mailing->ulid;

        $this->recipients($mailing)->each(function (User $u) use ($mailing, $title, $message, $url) {
            $already = $u->notifications()
                ->where('data->url', $url)
                ->where('data->title', $title)
                ->exists();

            if (! $already) {
                $u->notify(new MailingStatusChanged($mailing, $title, $message));
            }
        });
    }

    private function wasOnTime(ProposalMailing $mailing): bool
    {
        if ($mailing->deadline === null || $mailing->delivered_at === null) {
            return true;
        }

        return $mailing->delivered_at->copy()->startOfDay()->lte($mailing->deadline->copy()->startOfDay());
    }

    /** In-app bell alert on a notable status change. */
    private function alert(ProposalMailing $mailing, MailingStatus $status): void
    {
        [$title, $message] = match ($status) {
            MailingStatus::OutForDelivery => ['Out for delivery', $this->who($mailing).' is out for delivery today.'],
            MailingStatus::Delivered => [
                $mailing->on_time === false ? 'Delivered late' : 'Delivered on time',
                $this->who($mailing).' was delivered'.($mailing->received_by ? ' (received by '.$mailing->received_by.')' : '').'.',
            ],
            MailingStatus::Exception => ['Delivery exception', $this->who($mailing).' hit a delivery exception.'],
            MailingStatus::Returned => ['Returned to sender', $this->who($mailing).' is being returned to sender.'],
            default => [null, null],
        };

        if ($title === null) {
            return;
        }

        $this->recipients($mailing)
            ->each(fn (User $u) => $u->notify(new MailingStatusChanged($mailing, $title, $message)));
    }

    /** The mailing's creator + the linked proposal's owner (deduped). */
    private function recipients(ProposalMailing $mailing)
    {
        $mailing->loadMissing('proposalSubmission');

        $ids = collect([
            $mailing->created_by,
            $mailing->proposalSubmission?->owner_id,
        ])->filter()->unique();

        return User::whereIn('id', $ids)->get();
    }

    private function who(ProposalMailing $mailing): string
    {
        return $mailing->recipient_name
            ? "Mailing to {$mailing->recipient_name}"
            : "Mailing {$mailing->ups_tracking_number}";
    }
}
