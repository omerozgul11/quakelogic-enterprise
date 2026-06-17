<?php

namespace App\Services\Ai;

use App\Enums\DeliveryRisk;
use App\Enums\MailingStatus;
use App\Models\FollowUp;
use App\Models\Opportunity;
use App\Models\ProposalMailing;
use App\Models\ProposalSubmission;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Builds an organisation-scoped snapshot of the user's portal — open proposals,
 * opportunities, mailed-shipment (UPS) status and due follow-ups. Used two ways:
 * `forUser()` renders it as text grounding for QuakeBot (the AI), and
 * `snapshot()` returns the structured data the offline LocalAssistantResponder
 * answers from when the AI provider is unavailable. Respects the same visibility
 * rules the user has in the UI (proposal involvement, `view all proposals`,
 * `access shipments`).
 */
class PortalContextService
{
    /** Statuses that count as "still open / in flight" for a proposal. */
    public const OPEN_STATUSES = ['in_progress', 'submitted', 'award_pending', 'clarification_requested', 'protested'];

    /** @return array<string,mixed> */
    public function snapshot(User $user): array
    {
        $orgId = $user->organization_id;

        $pBase = ProposalSubmission::forOrganization($orgId);
        if (! $user->can('view all proposals')) {
            $pBase->where(fn ($q) => $q
                ->where('created_by', $user->id)
                ->orWhere('owner_id', $user->id)
                ->orWhere('proposal_manager_id', $user->id)
                ->orWhereHas('teamMembers', fn ($tm) => $tm->where('user_id', $user->id)));
        }

        $proposals = (clone $pBase)
            ->with(['owner:id,name', 'company:id,name', 'agency:id,name'])
            ->orderByRaw('due_date IS NULL')->orderBy('due_date')
            ->limit(40)->get();

        $statusCounts = (clone $pBase)->get(['status'])
            ->groupBy(fn ($p) => $p->status?->label() ?? 'Unknown')
            ->map->count()->toArray();

        $opportunities = Opportunity::where('organization_id', $orgId)->active()
            ->orderByRaw('response_deadline IS NULL')->orderBy('response_deadline')
            ->limit(25)->get();

        $canShip = $user->can('access shipments');
        $shipmentsActive = new Collection();
        $shipmentsDelivered = new Collection();
        if ($canShip) {
            $with = ['proposalSubmission:id,proposal_number,project_name'];
            $shipmentsActive = ProposalMailing::forOrganization($orgId)->active()->with($with)
                ->orderByRaw('deadline IS NULL')->orderBy('deadline')->limit(30)->get();
            $shipmentsDelivered = ProposalMailing::forOrganization($orgId)
                ->where('status', MailingStatus::Delivered->value)->with($with)
                ->latest('delivered_at')->limit(12)->get();
        }

        $followUps = FollowUp::where('assigned_to', $user->id)
            ->whereNull('responded_at')->whereNotNull('scheduled_date')
            ->with(['proposal:id,proposal_number', 'contact:id,first_name,last_name'])
            ->orderBy('scheduled_date')->limit(15)->get();

        return [
            'today' => now(),
            'proposals' => $proposals,
            'statusCounts' => $statusCounts,
            'opportunities' => $opportunities,
            'canShip' => $canShip,
            'shipmentsActive' => $shipmentsActive,
            'shipmentsDelivered' => $shipmentsDelivered,
            'followUps' => $followUps,
        ];
    }

    public function forUser(User $user): string
    {
        $s = $this->snapshot($user);

        $sections = array_filter([
            $this->textProposals($s),
            $this->textOpportunities($s),
            $this->textShipments($s),
            $this->textFollowUps($s),
        ]);

        return implode("\n\n", $sections);
    }

    private function textProposals(array $s): string
    {
        if ($s['proposals']->isEmpty()) {
            return 'PROPOSALS: none visible to this user.';
        }

        $counts = collect($s['statusCounts'])->map(fn ($n, $label) => "$label: $n")->implode(', ');

        $lines = $s['proposals']->map(fn (ProposalSubmission $p) => '- ' . implode(' | ', array_filter([
            $p->proposal_number,
            $p->project_name,
            'status: ' . ($p->status?->label() ?? '—'),
            $p->due_date ? 'due ' . $p->due_date->format('Y-m-d') : null,
            $p->proposal_value ? 'value ' . $this->money($p->proposal_value, $p->currency) : null,
            $p->owner?->name ? 'owner ' . $p->owner->name : null,
            $p->company?->name ? 'for ' . $p->company->name : null,
            $p->submission_date ? 'submitted ' . $p->submission_date->format('Y-m-d') : null,
            $p->award_date ? 'awarded ' . $p->award_date->format('Y-m-d') : null,
        ])))->implode("\n");

        return "PROPOSALS (visible to this user — counts: {$counts}):\n{$lines}";
    }

    private function textOpportunities(array $s): string
    {
        if ($s['opportunities']->isEmpty()) {
            return '';
        }

        $lines = $s['opportunities']->map(function (Opportunity $o) {
            $deadline = $o->response_deadline ?? $o->due_date;

            return '- ' . implode(' | ', array_filter([
                $o->title,
                $o->solicitation_number ? 'sol# ' . $o->solicitation_number : null,
                'status: ' . ($o->status?->label() ?? '—'),
                $deadline ? 'response due ' . $deadline->format('Y-m-d') : null,
                $o->estimated_value ? 'est. ' . $this->money($o->estimated_value, $o->currency) : null,
            ]));
        })->implode("\n");

        return "OPEN OPPORTUNITIES:\n{$lines}";
    }

    private function textShipments(array $s): string
    {
        if (! $s['canShip']) {
            return '';
        }

        $active = $s['shipmentsActive'];
        $delivered = $s['shipmentsDelivered'];

        if ($active->isEmpty() && $delivered->isEmpty()) {
            return 'SHIPMENTS (mailed proposals, UPS tracking): none recorded.';
        }

        $atRisk = $active->filter(fn ($m) => $m->risk() === DeliveryRisk::AtRisk)->count();

        $fmt = fn (ProposalMailing $m) => '- ' . implode(' | ', array_filter([
            $m->ups_tracking_number ? 'track# ' . $m->ups_tracking_number : 'no tracking#',
            $m->carrier ?: 'UPS',
            'status: ' . $m->status?->label(),
            'risk: ' . $m->risk()->label(),
            $m->deadline ? 'deadline ' . $m->deadline->format('Y-m-d') : null,
            $m->scheduled_delivery ? 'ETA ' . $m->scheduled_delivery->format('Y-m-d') : null,
            $m->delivered_at ? 'delivered ' . $m->delivered_at->format('Y-m-d') : null,
            $m->received_by ? 'received by ' . $m->received_by : null,
            $m->proposalSubmission ? 'proposal ' . ($m->proposalSubmission->proposal_number ?? $m->proposalSubmission->project_name) : 'unlinked',
        ]));

        $out = "SHIPMENTS (mailed proposals, UPS tracking — active: {$active->count()}, at risk: {$atRisk}, recently delivered shown: {$delivered->count()}):";
        if ($active->isNotEmpty()) {
            $out .= "\nIn progress:\n" . $active->map($fmt)->implode("\n");
        }
        if ($delivered->isNotEmpty()) {
            $out .= "\nRecently delivered:\n" . $delivered->map($fmt)->implode("\n");
        }

        return $out;
    }

    private function textFollowUps(array $s): string
    {
        if ($s['followUps']->isEmpty()) {
            return '';
        }

        $lines = $s['followUps']->map(fn (FollowUp $f) => '- ' . implode(' | ', array_filter([
            $f->subject ?: ($f->type ?: 'Follow-up'),
            'due ' . $f->scheduled_date->format('Y-m-d'),
            'status: ' . ($f->status?->label() ?? '—'),
            $f->proposal?->proposal_number ? 'proposal ' . $f->proposal->proposal_number : null,
            $f->contact?->full_name ? 'contact ' . $f->contact->full_name : null,
        ])))->implode("\n");

        return "MY FOLLOW-UPS DUE:\n{$lines}";
    }

    public function money(int|float|string|null $value, ?string $currency): string
    {
        if ($value === null) {
            return '—';
        }

        $symbol = match (strtoupper((string) $currency)) {
            '', 'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            default => '',
        };

        $formatted = $symbol . number_format((float) $value);

        return $symbol === '' ? $formatted . ' ' . strtoupper((string) $currency) : $formatted;
    }
}
