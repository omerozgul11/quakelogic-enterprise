<?php

namespace App\Services\Mailings;

use App\Enums\MailingStatus;
use App\Models\MailingTrackingEvent;
use App\Models\Organization;
use App\Models\ProposalMailing;
use App\Models\ProposalSubmission;
use App\Models\User;
use App\Services\Ups\QuantumView\QuantumViewClient;
use Illuminate\Support\Carbon;

/**
 * Auto-ingests account shipments from UPS Quantum View into proposal_mailings:
 * new tracking numbers become mailings (auto-linked to a proposal when the
 * shipment carries the proposal number as a reference), and later activity
 * advances status. The regular mailings:poll then enriches each with its full
 * tracking timeline from the Tracking API.
 */
class MailingIngestService
{
    public function __construct(private readonly QuantumViewClient $qv) {}

    /** @return array{created:int,updated:int} */
    public function ingest(Carbon $since): array
    {
        $orgId = (int) (config('services.ups.quantum_view.organization_id') ?: Organization::min('id'));
        $creatorId = $orgId ? $this->systemUserId($orgId) : null;

        if (! $orgId || ! $creatorId) {
            return ['created' => 0, 'updated' => 0];
        }

        $created = 0;
        $updated = 0;

        foreach ($this->qv->fetch($since) as $a) {
            $mailing = ProposalMailing::where('organization_id', $orgId)
                ->where('ups_tracking_number', $a->trackingNumber)->first();

            if (! $mailing) {
                $mailing = new ProposalMailing([
                    'ups_tracking_number' => $a->trackingNumber,
                    'recipient_name' => $a->recipientName,
                    'scheduled_delivery' => $a->scheduledDelivery,
                ]);
                $mailing->organization_id = $orgId;
                $mailing->created_by = $creatorId;
                $mailing->carrier = 'ups';
                $mailing->status = $this->statusFor($a->type);
                $mailing->proposal_submission_id = $this->matchProposal($orgId, $a->references);
                $proposal = $mailing->proposal_submission_id
                    ? ProposalSubmission::find($mailing->proposal_submission_id) : null;
                $mailing->deadline = $proposal?->due_date;
                $mailing->save();
                $created++;
            } else {
                $mailing->status = $this->statusFor($a->type, $mailing->status);
                if ($a->scheduledDelivery) {
                    $mailing->scheduled_delivery = $a->scheduledDelivery;
                }
                if ($a->type === 'delivery' && ! $mailing->delivered_at) {
                    $mailing->delivered_at = $a->occurredAt;
                    $mailing->on_time = $this->onTime($mailing, $a->occurredAt);
                }
                $mailing->save();
                $updated++;
            }

            if ($a->occurredAt) {
                MailingTrackingEvent::firstOrCreate(
                    ['proposal_mailing_id' => $mailing->id, 'code' => strtoupper($a->type), 'occurred_at' => $a->occurredAt],
                    ['description' => $a->description ?? ucfirst($a->type), 'location' => $a->location],
                );
            }
        }

        return ['created' => $created, 'updated' => $updated];
    }

    private function statusFor(string $type, ?MailingStatus $current = null): MailingStatus
    {
        if ($current && $current->isTerminal()) {
            return $current;
        }

        return match ($type) {
            'manifest' => MailingStatus::LabelCreated,
            'origin' => MailingStatus::InTransit,
            'delivery' => MailingStatus::Delivered,
            'exception' => MailingStatus::Exception,
            default => $current ?? MailingStatus::InTransit,
        };
    }

    private function onTime(ProposalMailing $mailing, ?Carbon $deliveredAt): ?bool
    {
        if (! $mailing->deadline || ! $deliveredAt) {
            return null;
        }

        return $deliveredAt->copy()->startOfDay()->lte($mailing->deadline->copy()->startOfDay());
    }

    private function matchProposal(int $orgId, array $references): ?int
    {
        if (! $references) {
            return null;
        }

        return ProposalSubmission::where('organization_id', $orgId)
            ->whereIn('proposal_number', $references)
            ->value('id');
    }

    private function systemUserId(int $orgId): ?int
    {
        return User::where('organization_id', $orgId)->role('Super Admin')->value('id')
            ?? User::where('organization_id', $orgId)->value('id');
    }
}
