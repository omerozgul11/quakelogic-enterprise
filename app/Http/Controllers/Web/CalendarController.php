<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Opportunity;
use App\Models\ProposalSubmission;
use App\Support\Currency;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CalendarController extends Controller
{
    /**
     * Calendar of everything date-bound in the pipeline. Events are derived
     * automatically: every proposal (by its due date) plus the opportunities
     * that have actually been picked up — i.e. a proposal draft was started for
     * them (by their due date). Raw imported opportunities are NOT shown.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $orgId = $user->organization_id;

        // A window wide enough for month-to-month navigation either side of now.
        $from = now()->startOfMonth()->subMonth()->toDateString();
        $to = now()->addMonths(12)->endOfMonth()->toDateString();

        // Only an administrator sees everyone's items on the calendar. Every other
        // user sees only the proposals they own or are attached to (team member).
        $isAdmin = $user->hasRole('Super Admin');

        $proposalsQuery = ProposalSubmission::forOrganization($orgId)
            ->whereNotNull('due_date')
            ->whereBetween('due_date', [$from, $to])
            ->with('company:id,name');

        if (!$isAdmin) {
            $proposalsQuery->where(fn ($q) => $q
                ->where('owner_id', $user->id)
                ->orWhere('proposal_manager_id', $user->id)
                ->orWhereHas('teamMembers', fn ($tm) => $tm->where('user_id', $user->id)));
        }

        $proposals = $proposalsQuery->get()->map(fn ($p) => [
            'id' => 'proposal-' . $p->id,
            'type' => 'proposal',
            'date' => $p->due_date?->format('Y-m-d'),
            'title' => $p->project_name,
            'subtitle' => trim($p->proposal_number . ($p->company ? ' · ' . $p->company->name : '')),
            'status' => $p->status instanceof \BackedEnum ? $p->status->value : $p->status,
            'value' => (float) $p->proposal_value,
            'currency' => $p->currency ?? Currency::DEFAULT,
            'url' => "/proposals/{$p->id}",
        ]);

        // Opportunities — only the ones that have actually been picked up, i.e. a
        // proposal draft was started for them (not the raw imported firehose).
        // Visibility mirrors the proposal rule: non-admins only see opportunities
        // they started a proposal for (own / manager / team).
        $opportunities = Opportunity::forOrganization($orgId)
            ->active()
            ->whereNotNull('due_date')
            ->whereBetween('due_date', [$from, $to])
            ->whereHas('proposals', function ($q) use ($user, $isAdmin) {
                if (!$isAdmin) {
                    $q->where(fn ($qq) => $qq
                        ->where('owner_id', $user->id)
                        ->orWhere('proposal_manager_id', $user->id)
                        ->orWhereHas('teamMembers', fn ($tm) => $tm->where('user_id', $user->id)));
                }
            })
            ->get(['id', 'title', 'solicitation_number', 'agency_name', 'status', 'estimated_value', 'currency', 'due_date'])
            ->map(fn ($o) => [
                'id' => 'opportunity-' . $o->id,
                'type' => 'opportunity',
                'date' => $o->due_date?->format('Y-m-d'),
                'title' => $o->title,
                'subtitle' => $o->agency_name ?: ($o->solicitation_number ?: 'Opportunity'),
                'status' => $o->status instanceof \BackedEnum ? $o->status->value : $o->status,
                'value' => (float) $o->estimated_value,
                'currency' => $o->currency ?? Currency::DEFAULT,
                'url' => "/opportunities/{$o->id}",
            ]);

        $events = $proposals->concat($opportunities)->values();

        return Inertia::render('Calendar/Index', [
            'events' => $events,
            'counts' => [
                'proposals' => $proposals->count(),
                'opportunities' => $opportunities->count(),
            ],
            'can' => [
                'createProposal' => $user->can('create proposals'),
                'createOpportunity' => $user->can('create opportunities'),
            ],
        ]);
    }
}
