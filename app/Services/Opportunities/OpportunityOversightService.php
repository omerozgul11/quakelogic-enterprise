<?php

namespace App\Services\Opportunities;

use App\Enums\OpportunityAssignmentStage;
use App\Models\FollowUp;
use App\Models\Opportunity;
use App\Models\OpportunityUserState;
use App\Models\ProposalSubmission;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Powers the executive oversight dashboard and the daily briefing: one place to
 * see every opportunity discovered, who owns it, who hasn't acted, what's at
 * risk, who's overloaded, and what to reassign. All organization-scoped and
 * computed from live data.
 */
class OpportunityOversightService
{
    public function __construct(
        private readonly OpportunityHealthService $health,
    ) {}

    /** @return array<string,mixed> */
    public function summary(int $organizationId): array
    {
        return [
            'counts' => $this->counts($organizationId),
            'metrics' => $this->metrics($organizationId),
            'workload' => $this->workload($organizationId),
            'at_risk' => $this->atRisk($organizationId),
            'reassignments' => $this->reassignmentCandidates($organizationId),
            'attention' => $this->attention($organizationId),
        ];
    }

    private function active(int $organizationId): Builder
    {
        return Opportunity::forOrganization($organizationId)->active();
    }

    /** @return array<string,int> */
    public function counts(int $organizationId): array
    {
        $stage = fn (OpportunityAssignmentStage $s) => Opportunity::forOrganization($organizationId)
            ->where('assignment_stage', $s->value)->count();

        return [
            'discovered_today' => Opportunity::forOrganization($organizationId)->whereDate('created_at', now()->toDateString())->count(),
            'active' => $this->active($organizationId)->count(),
            'assigned' => $this->active($organizationId)->whereNotNull('owner_id')->count(),
            'unassigned' => $this->active($organizationId)->whereNull('owner_id')->count(),
            'overdue' => $this->active($organizationId)->whereRaw('COALESCE(response_deadline, due_date) < ?', [now()->toDateString()])->count(),
            'inactive' => $this->active($organizationId)->whereNotNull('owner_id')->where('last_activity_at', '<', now()->subDays(7))->count(),
            'submitted' => $stage(OpportunityAssignmentStage::Submitted),
            'won' => $stage(OpportunityAssignmentStage::Won),
            'lost' => $stage(OpportunityAssignmentStage::Lost),
            'abandoned' => $stage(OpportunityAssignmentStage::Abandoned),
        ];
    }

    /** @return array<string,mixed> */
    public function metrics(int $organizationId): array
    {
        $won = Opportunity::forOrganization($organizationId)->where('assignment_stage', 'won')->count();
        $lost = Opportunity::forOrganization($organizationId)->where('assignment_stage', 'lost')->count();
        $closed = $won + $lost;

        $totalActive = $this->active($organizationId)->count();
        $assigned = $this->active($organizationId)->whereNotNull('owner_id')->count();

        $responseHours = Opportunity::forOrganization($organizationId)
            ->whereNotNull('assigned_at')->whereNotNull('accepted_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, assigned_at, accepted_at)) as v')->value('v');

        $submissionDays = DB::table('opportunities')
            ->join('proposal_submissions', 'proposal_submissions.opportunity_id', '=', 'opportunities.id')
            ->where('opportunities.organization_id', $organizationId)
            ->whereNotNull('opportunities.assigned_at')
            ->whereNotNull('proposal_submissions.submission_date')
            ->selectRaw('AVG(DATEDIFF(proposal_submissions.submission_date, opportunities.assigned_at)) as v')->value('v');

        $velocity = ProposalSubmission::forOrganization($organizationId)
            ->whereNotNull('submission_date')
            ->where('submission_date', '>=', now()->subDays(28))
            ->count() / 4;

        return [
            'win_rate' => $closed > 0 ? round($won / $closed * 100, 1) : null,
            'assignment_rate' => $totalActive > 0 ? round($assigned / $totalActive * 100, 1) : null,
            'avg_response_hours' => $responseHours !== null ? round((float) $responseHours, 1) : null,
            'avg_submission_days' => $submissionDays !== null ? round((float) $submissionDays, 1) : null,
            'proposal_velocity_per_week' => round($velocity, 1),
        ];
    }

    /** @return array{highest:array<int,array<string,mixed>>,lowest:array<int,array<string,mixed>>} */
    public function workload(int $organizationId): array
    {
        $users = User::where('organization_id', $organizationId)
            ->where('is_active', true)
            ->get(['id', 'name', 'workload_score'])
            ->filter(fn (User $u) => $u->can('update opportunities') || $u->can('create proposals'))
            ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name, 'workload' => (int) ($u->workload_score ?? 0)])
            ->values();

        $sorted = $users->sortByDesc('workload')->values();

        return [
            'highest' => $sorted->take(5)->all(),
            'lowest' => $sorted->reverse()->take(5)->values()->all(),
        ];
    }

    /** @return array{warning:int,critical:int,items:array<int,array<string,mixed>>} */
    public function atRisk(int $organizationId): array
    {
        $query = $this->active($organizationId)
            ->whereNotNull('owner_id')
            ->where(function ($q) {
                $q->whereRaw('COALESCE(response_deadline, due_date) < ?', [now()->toDateString()])
                    ->orWhere('last_activity_at', '<', now()->subDays(14))
                    ->orWhere('assignment_escalation_level', '>=', 72);
            });

        $count = (clone $query)->count();
        $items = $query->with('owner:id,name')->orderBy('last_activity_at')->limit(12)->get()
            ->map(function (Opportunity $o) {
                $h = $this->health->score($o);

                return [
                    'id' => $o->id,
                    'title' => $o->title,
                    'owner' => $o->owner?->name,
                    'stage' => $o->assignment_stage?->label(),
                    'days_until_deadline' => $o->days_until_deadline,
                    'days_since_activity' => $o->days_since_activity,
                    'health' => $h['score'],
                    'category' => $h['category'],
                ];
            });

        return [
            'warning' => $items->where('category', 'warning')->count(),
            'critical' => $items->where('category', 'critical')->count(),
            'flagged_total' => $count,
            'items' => $items->values()->all(),
        ];
    }

    /**
     * Opportunities that have hit the 96h reassignment tier or gone long without
     * activity, with a suggested better-fit owner (highest match, not the
     * current owner). @return array<int,array<string,mixed>>
     */
    public function reassignmentCandidates(int $organizationId): array
    {
        $candidates = $this->active($organizationId)
            ->whereNotNull('owner_id')
            ->where(function ($q) {
                $q->where('assignment_escalation_level', '>=', 96)
                    ->orWhere('last_activity_at', '<', now()->subDays(21));
            })
            ->with('owner:id,name')
            ->limit(10)
            ->get();

        return $candidates->map(function (Opportunity $o) {
            $suggested = OpportunityUserState::where('opportunity_id', $o->id)
                ->where('user_id', '!=', $o->owner_id)
                ->where('match_score', '>=', OpportunityMatchingService::RECOMMEND_THRESHOLD)
                ->with('user:id,name')
                ->orderByDesc('match_score')
                ->first();

            return [
                'id' => $o->id,
                'title' => $o->title,
                'current_owner' => $o->owner?->name,
                'days_since_activity' => $o->days_since_activity,
                'escalation_level' => (int) $o->assignment_escalation_level,
                'suggested_owner' => $suggested?->user?->name,
                'suggested_score' => $suggested && $suggested->match_score !== null ? (float) $suggested->match_score : null,
            ];
        })->filter(fn ($r) => $r['suggested_owner'] !== null)->values()->all();
    }

    /**
     * The "show everything requiring attention today" buckets.
     *
     * @return array<string,array<int,array<string,mixed>>>
     */
    public function attention(int $organizationId): array
    {
        $missedFollowUps = FollowUp::where('organization_id', $organizationId)
            ->whereNotIn('type', ['digest'])
            ->where(function ($q) {
                $q->where('status', 'overdue')
                    ->orWhere(fn ($q2) => $q2->where('status', 'scheduled')->whereDate('scheduled_date', '<', now()->toDateString()));
            })
            ->with('assignedTo:id,name')
            ->latest('scheduled_date')->limit(8)->get()
            ->map(fn (FollowUp $f) => [
                'id' => $f->id,
                'subject' => $f->subject ?: ucfirst((string) $f->type),
                'assigned_to' => $f->assignedTo?->name,
                'scheduled_date' => $f->scheduled_date?->toDateString(),
            ])->all();

        $inactive = $this->active($organizationId)
            ->whereNotNull('owner_id')
            ->where('last_activity_at', '<', now()->subDays(7))
            ->with('owner:id,name')
            ->orderBy('last_activity_at')->limit(8)->get()
            ->map(fn (Opportunity $o) => $this->oppRow($o))->all();

        $unassigned = $this->active($organizationId)
            ->whereNull('owner_id')
            ->with('owner:id,name')
            ->orderByRaw('COALESCE(response_deadline, due_date) IS NULL')
            ->orderByRaw('COALESCE(response_deadline, due_date) ASC')
            ->limit(8)->get()
            ->map(fn (Opportunity $o) => $this->oppRow($o))->all();

        $upcoming = $this->active($organizationId)
            ->whereRaw('COALESCE(response_deadline, due_date) BETWEEN ? AND ?', [now()->toDateString(), now()->addDays(7)->toDateString()])
            ->with('owner:id,name')
            ->orderByRaw('COALESCE(response_deadline, due_date) ASC')
            ->limit(8)->get()
            ->map(fn (Opportunity $o) => $this->oppRow($o))->all();

        $pendingResponses = FollowUp::where('organization_id', $organizationId)
            ->whereNotNull('contact_id')
            ->whereNotNull('sent_at')
            ->whereNull('responded_at')
            ->where('status', '!=', 'cancelled')
            ->with('assignedTo:id,name')
            ->latest('sent_at')->limit(8)->get()
            ->map(fn (FollowUp $f) => [
                'id' => $f->id,
                'subject' => $f->subject ?: 'Client follow-up',
                'assigned_to' => $f->assignedTo?->name,
                'sent_at' => $f->sent_at?->toDateString(),
            ])->all();

        $pendingReviews = ProposalSubmission::forOrganization($organizationId)
            ->whereIn('status', ['award_pending', 'clarification_requested'])
            ->with('owner:id,name')
            ->limit(8)->get()
            ->map(fn (ProposalSubmission $p) => [
                'id' => $p->id,
                'title' => $p->project_name,
                'number' => $p->proposal_number,
                'status' => $p->status?->label(),
                'owner' => $p->owner?->name,
            ])->all();

        return [
            'missed_follow_ups' => $missedFollowUps,
            'inactive_opportunities' => $inactive,
            'unassigned_opportunities' => $unassigned,
            'upcoming_deadlines' => $upcoming,
            'pending_customer_responses' => $pendingResponses,
            'pending_proposal_reviews' => $pendingReviews,
        ];
    }

    /** @return array<string,mixed> */
    private function oppRow(Opportunity $o): array
    {
        return [
            'id' => $o->id,
            'title' => $o->title,
            'agency' => $o->agency_name,
            'owner' => $o->owner?->name,
            'stage' => $o->assignment_stage?->label(),
            'days_until_deadline' => $o->days_until_deadline,
            'value' => $o->estimated_value !== null ? (float) $o->estimated_value : null,
            'currency' => $o->currency,
        ];
    }
}
