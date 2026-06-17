<?php

namespace App\Services\Mailings;

use App\Models\ProposalMailing;
use App\Models\ProposalSubmission;
use Illuminate\Support\Collection;

/**
 * Links shipments to the proposals they belong to without anyone having to do it
 * by hand. For each unlinked shipment we score every unlinked proposal on a few
 * signals — the proposal/solicitation number printed on the label, the recipient
 * matching the issuing agency, the deadline matching the proposal's due date, the
 * destination address — and link the clear winner. Conservative on purpose: it
 * only links a confident, unambiguous match, so it never guesses wrong.
 */
class ShipmentProposalMatcher
{
    /** Minimum score to accept a link. */
    private const THRESHOLD = 3;

    /**
     * Link every unlinked shipment in an organization to its best proposal.
     * Returns how many were newly linked.
     */
    public function matchOrganization(int $orgId, int $maxShipments = 500): int
    {
        $proposals = $this->candidateProposals($orgId);
        if ($proposals->isEmpty()) {
            return 0;
        }

        $shipments = ProposalMailing::query()
            ->forOrganization($orgId)
            ->whereNull('proposal_submission_id')
            ->orderByDesc('id')
            ->limit($maxShipments)
            ->get();

        $linked = 0;
        $taken = [];

        foreach ($shipments as $m) {
            $available = $proposals->reject(fn (ProposalSubmission $p) => isset($taken[$p->id]));
            $best = $this->bestMatch($m, $available);
            if ($best) {
                $m->proposal_submission_id = $best->id;
                if (! $m->deadline && $best->due_date) {
                    $m->deadline = $best->due_date;
                }
                $m->save();
                $taken[$best->id] = true;
                $linked++;
            }
        }

        return $linked;
    }

    /** Try to link a single shipment in place. Returns the linked proposal, if any. */
    public function matchOne(ProposalMailing $m): ?ProposalSubmission
    {
        if ($m->proposal_submission_id) {
            return null;
        }

        $best = $this->bestMatch($m, $this->candidateProposals($m->organization_id));
        if ($best) {
            $m->proposal_submission_id = $best->id;
            if (! $m->deadline && $best->due_date) {
                $m->deadline = $best->due_date;
            }
            $m->save();
        }

        return $best;
    }

    /** @return Collection<int, ProposalSubmission> */
    private function candidateProposals(int $orgId): Collection
    {
        // Full models (no column restriction) so strict-mode attribute access is safe.
        return ProposalSubmission::query()
            ->forOrganization($orgId)
            ->whereDoesntHave('mailing')
            ->with('agency')
            ->orderByDesc('due_date')
            ->limit(1000)
            ->get();
    }

    /** @param  Collection<int, ProposalSubmission>  $proposals */
    private function bestMatch(ProposalMailing $m, Collection $proposals): ?ProposalSubmission
    {
        $recipient = $this->norm($m->recipient_name);
        $address = $this->norm($m->recipient_address);
        $haystack = trim($recipient.' '.$address);

        if ($haystack === '' && ! $m->deadline) {
            return null;
        }

        $scored = [];
        foreach ($proposals as $p) {
            $score = 0;

            // The proposal/solicitation number printed on the label is the
            // strongest signal there is.
            foreach ([$p->proposal_number, $p->solicitation_number] as $num) {
                $n = $this->norm($num);
                if ($n !== '' && strlen($n) >= 4 && str_contains(str_replace(' ', '', $haystack), str_replace(' ', '', $n))) {
                    $score += 4;
                    break;
                }
            }

            // Recipient ↔ issuing agency (name or acronym).
            $agency = $this->norm($p->agency?->name);
            $acronym = $this->norm($p->agency?->acronym);
            if ($recipient !== '' && $agency !== '') {
                if ($recipient === $agency) {
                    $score += 3;
                } elseif ($this->contains($recipient, $agency) || $this->contains($agency, $recipient)) {
                    $score += 2;
                }
            }
            if ($recipient !== '' && $acronym !== '' && strlen($acronym) >= 2 && $this->contains($recipient, $acronym)) {
                $score += 1;
            }

            // Deadline ↔ due date.
            if ($m->deadline && $p->due_date && $m->deadline->isSameDay($p->due_date)) {
                $score += 2;
            }

            // Destination address ↔ agency city/zip/state.
            if ($address !== '' && $p->agency) {
                foreach ([$p->agency->zip, $p->agency->city, $p->agency->state] as $piece) {
                    $pn = $this->norm($piece);
                    if ($pn !== '' && strlen($pn) >= 2 && str_contains($address, $pn)) {
                        $score += 1;
                        break;
                    }
                }
            }

            if ($score > 0) {
                $scored[] = ['p' => $p, 's' => $score];
            }
        }

        if ($scored === []) {
            return null;
        }

        usort($scored, fn ($a, $b) => $b['s'] <=> $a['s']);
        $top = $scored[0];

        // Need a confident, unambiguous winner.
        if ($top['s'] < self::THRESHOLD) {
            return null;
        }
        if (isset($scored[1]) && $scored[1]['s'] === $top['s']) {
            return null;
        }

        return $top['p'];
    }

    private function contains(string $haystack, string $needle): bool
    {
        return strlen($needle) >= 5 && str_contains($haystack, $needle);
    }

    private function norm(?string $value): string
    {
        $v = strtolower(trim((string) $value));
        $v = preg_replace('/[^a-z0-9 ]+/', ' ', $v) ?? $v;

        return trim(preg_replace('/\s+/', ' ', $v) ?? $v);
    }
}
