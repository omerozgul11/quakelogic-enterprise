<?php

namespace App\Services\Ai;

use App\Enums\DeliveryRisk;
use App\Models\FollowUp;
use App\Models\Opportunity;
use App\Models\ProposalMailing;
use App\Models\ProposalSubmission;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * A lightweight, keyword-driven assistant that answers QuakeBot questions
 * directly from the portal snapshot — no external API. It's the graceful
 * fallback used when the AI provider is unavailable (e.g. no API credits, or an
 * outage), so the chatbot keeps working for the common "what's due / what's at
 * risk / where's my shipment" questions. Returns plain-text answers.
 */
class LocalAssistantResponder
{
    public function __construct(private readonly PortalContextService $context) {}

    public function answerForUser(\App\Models\User $user, string $message): string
    {
        return $this->answer($message, $this->context->snapshot($user));
    }

    /** @param array<string,mixed> $s */
    public function answer(string $message, array $s): string
    {
        $q = strtolower($message);

        return match (true) {
            // follow-up is checked first: "follow-ups" contains "ups" which the
            // shipment matcher would otherwise grab as the UPS carrier.
            (bool) preg_match('/\b(follow[\s-]?ups?|followups?|reminders?)\b/', $q) => $this->followUps($s),
            (bool) preg_match('/\b(ships?|shipments?|track(ing|s)?|deliver(y|ies|ed)?|packages?|parcels?|mail(s|ed|ing)?|ups)\b/', $q) => $this->shipments($q, $s),
            (bool) preg_match('/\b(opportunit(y|ies)|rfps?|solicitations?|new bids?)\b/', $q) => $this->opportunities($s),
            (bool) preg_match('/\b(due|deadlines?|overdue|this week|soon|upcoming|expiring)\b/', $q) => $this->dueProposals($s),
            (bool) preg_match('/\b(proposals?|awards?|pipeline|status(es)?|how many|counts?|submissions?)\b/', $q) => $this->proposals($s),
            (bool) preg_match('/\b(attention|today|summary|overview|going on|catch me up|update me)\b/', $q) => $this->summary($s),
            default => $this->fallback($s),
        };
    }

    private function shipments(string $q, array $s): string
    {
        if (! ($s['canShip'] ?? false)) {
            return "You don't have access to the Shipments section, so I can't pull tracking details. Ask an admin if you think you should.";
        }

        /** @var Collection $active */
        $active = $s['shipmentsActive'];
        /** @var Collection $delivered */
        $delivered = $s['shipmentsDelivered'];

        if (str_contains($q, 'risk')) {
            $atRisk = $active->filter(fn (ProposalMailing $m) => $m->risk() === DeliveryRisk::AtRisk);
            if ($atRisk->isEmpty()) {
                return 'Good news — none of your active shipments are flagged at risk right now.';
            }

            return "⚠️ {$atRisk->count()} shipment(s) at risk of missing the deadline:\n" . $this->shipmentLines($atRisk);
        }

        if (str_contains($q, 'deliver')) {
            if ($delivered->isEmpty()) {
                return 'No shipments have been marked delivered yet.';
            }

            return "Recently delivered:\n" . $this->shipmentLines($delivered);
        }

        if ($active->isEmpty() && $delivered->isEmpty()) {
            return 'There are no shipments recorded yet. You can create one from a proposal or the Shipments section.';
        }

        $atRisk = $active->filter(fn (ProposalMailing $m) => $m->risk() === DeliveryRisk::AtRisk)->count();
        $head = "You have {$active->count()} active shipment(s)" . ($atRisk ? ", {$atRisk} at risk" : '') . ", and {$delivered->count()} recently delivered.";

        return $active->isNotEmpty()
            ? $head . "\nIn progress:\n" . $this->shipmentLines($active->take(8))
            : $head;
    }

    private function proposals(array $s): string
    {
        /** @var Collection $proposals */
        $proposals = $s['proposals'];
        if ($proposals->isEmpty()) {
            return "I don't see any proposals you have access to.";
        }

        $counts = collect($s['statusCounts'])->map(fn ($n, $label) => "$label: $n")->implode(', ');
        $total = collect($s['statusCounts'])->sum();

        $open = $proposals->filter(fn (ProposalSubmission $p) => in_array($p->status?->value, PortalContextService::OPEN_STATUSES, true));
        $show = $open->isNotEmpty() ? $open : $proposals;
        $label = $open->isNotEmpty() ? 'Open work, soonest by due date' : 'Most recent';

        return "You have {$total} proposal(s) — {$counts}.\n{$label}:\n"
            . $this->proposalLines($show->take(6));
    }

    private function dueProposals(array $s): string
    {
        /** @var Collection $proposals */
        $proposals = $s['proposals'];
        $today = $s['today'] instanceof Carbon ? $s['today']->copy()->startOfDay() : now()->startOfDay();
        $weekEnd = $today->copy()->addDays(7);

        // "Due" work = open proposals not yet submitted with a deadline.
        $open = $proposals->filter(fn (ProposalSubmission $p) => $p->due_date
            && $p->submission_date === null
            && in_array($p->status?->value, PortalContextService::OPEN_STATUSES, true));

        $overdue = $open->filter(fn ($p) => $p->due_date->lt($today));
        $thisWeek = $open->filter(fn ($p) => $p->due_date->gte($today) && $p->due_date->lte($weekEnd));
        $later = $open->filter(fn ($p) => $p->due_date->gt($weekEnd));

        $parts = [];
        if ($overdue->isNotEmpty()) {
            $parts[] = "⏰ Overdue ({$overdue->count()}):\n" . $this->proposalLines($overdue);
        }
        if ($thisWeek->isNotEmpty()) {
            $parts[] = "This week ({$thisWeek->count()}):\n" . $this->proposalLines($thisWeek);
        }
        if ($later->isNotEmpty() && $overdue->isEmpty() && $thisWeek->isEmpty()) {
            $parts[] = "Nothing due this week. Next up:\n" . $this->proposalLines($later->take(5));
        }

        return $parts ? implode("\n\n", $parts) : 'No open proposals have an upcoming due date.';
    }

    private function opportunities(array $s): string
    {
        /** @var Collection $opps */
        $opps = $s['opportunities'];
        if ($opps->isEmpty()) {
            return 'There are no open opportunities right now.';
        }

        $lines = $opps->take(8)->map(function (Opportunity $o) {
            $deadline = $o->response_deadline ?? $o->due_date;

            return '• ' . implode(' — ', array_filter([
                $o->title,
                $deadline ? 'due ' . $deadline->format('M j, Y') : null,
                $o->estimated_value ? $this->context->money($o->estimated_value, $o->currency) : null,
            ]));
        })->implode("\n");

        return "{$opps->count()} open opportunit(y/ies):\n{$lines}";
    }

    private function followUps(array $s): string
    {
        /** @var Collection $items */
        $items = $s['followUps'];
        if ($items->isEmpty()) {
            return 'You have no follow-ups due. Nicely done.';
        }

        $lines = $items->take(10)->map(fn (FollowUp $f) => '• ' . implode(' — ', array_filter([
            $f->subject ?: ($f->type ?: 'Follow-up'),
            'due ' . $f->scheduled_date->format('M j, Y'),
            $f->proposal?->proposal_number,
            $f->contact?->full_name,
        ])))->implode("\n");

        return "You have {$items->count()} follow-up(s) due:\n{$lines}";
    }

    private function summary(array $s): string
    {
        $today = $s['today'] instanceof Carbon ? $s['today']->copy()->startOfDay() : now()->startOfDay();

        $overdue = $s['proposals']->filter(fn (ProposalSubmission $p) => $p->due_date
            && $p->due_date->lt($today)
            && $p->submission_date === null
            && in_array($p->status?->value, PortalContextService::OPEN_STATUSES, true))->count();

        $atRisk = ($s['canShip'] ?? false)
            ? $s['shipmentsActive']->filter(fn (ProposalMailing $m) => $m->risk() === DeliveryRisk::AtRisk)->count()
            : 0;

        $bits = [
            "📂 {$s['proposals']->count()} proposal(s) on your radar" . ($overdue ? " ({$overdue} overdue)" : ''),
            $s['followUps']->isNotEmpty() ? "📞 {$s['followUps']->count()} follow-up(s) due" : null,
            ($s['canShip'] ?? false) ? "📦 {$s['shipmentsActive']->count()} active shipment(s)" . ($atRisk ? " ({$atRisk} at risk)" : '') : null,
            $s['opportunities']->isNotEmpty() ? "🎯 {$s['opportunities']->count()} open opportunit(y/ies)" : null,
        ];

        return "Here's where things stand:\n" . collect($bits)->filter()->map(fn ($b) => "• $b")->implode("\n")
            . "\n\nAsk me about deadlines, shipments at risk, or your follow-ups for detail.";
    }

    private function fallback(array $s): string
    {
        return "I can answer questions about your proposals, deadlines, opportunities, follow-ups and mailed-proposal shipments. Try:\n"
            . "• \"Which proposals are due this week?\"\n"
            . "• \"Are any shipments at risk?\"\n"
            . "• \"What follow-ups do I have?\"\n\n"
            . $this->summary($s);
    }

    private function proposalLines(Collection $proposals): string
    {
        return $proposals->map(fn (ProposalSubmission $p) => '• ' . implode(' — ', array_filter([
            $p->proposal_number,
            $p->project_name,
            $p->status?->label(),
            $p->due_date ? 'due ' . $p->due_date->format('M j, Y') : null,
        ])))->implode("\n");
    }

    private function shipmentLines(Collection $shipments): string
    {
        return $shipments->map(fn (ProposalMailing $m) => '• ' . implode(' — ', array_filter([
            $m->ups_tracking_number ?: 'no tracking#',
            $m->status?->label(),
            $m->risk()->label(),
            $m->deadline ? 'deadline ' . $m->deadline->format('M j') : null,
            $m->scheduled_delivery ? 'ETA ' . $m->scheduled_delivery->format('M j') : null,
            $m->proposalSubmission?->proposal_number,
        ])))->implode("\n");
    }
}
