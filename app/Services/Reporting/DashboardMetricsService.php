<?php

namespace App\Services\Reporting;

use App\Models\Commission;
use App\Models\FollowUp;
use App\Models\Opportunity;
use App\Models\ProposalSubmission;
use App\Models\Task;
use App\Models\User;
use App\Support\Currency;
use Illuminate\Support\Facades\DB;

class DashboardMetricsService
{
    public function getExecutiveDashboard(int $organizationId): array
    {
        $currentYear = now()->year;
        $currentMonth = now()->month;

        $proposals = ProposalSubmission::forOrganization($organizationId);
        $opportunities = Opportunity::forOrganization($organizationId);

        $won = \App\Enums\ProposalStatus::wonValues();

        $totalProposals = (clone $proposals)->count();
        $awarded = (clone $proposals)->whereIn('status', $won)->count();
        $lost = (clone $proposals)->where('status', 'lost')->count();
        $closed = $awarded + $lost;

        $winRate = $closed > 0 ? round(($awarded / $closed) * 100, 1) : 0;
        $lossRate = $closed > 0 ? round(($lost / $closed) * 100, 1) : 0;

        // All monetary totals are normalised to USD (proposals may be in any
        // currency) so the executive dashboard is comparable company-wide.
        $pipelineValue = (clone $proposals)
            ->whereIn('status', ['in_progress', 'submitted', 'award_pending', 'clarification_requested', 'protested'])
            ->sum(DB::raw(Currency::usdExpr('proposal_value')));

        // Earnings (YTD): value of contracts awarded this calendar year.
        $awardValue = (clone $proposals)
            ->whereIn('status', $won)
            ->whereYear('award_date', $currentYear)
            ->sum(DB::raw(Currency::usdExpr('COALESCE(NULLIF(award_value, 0), proposal_value)')));

        $submittedThisMonth = (clone $proposals)
            ->whereYear('submission_date', $currentYear)
            ->whereMonth('submission_date', $currentMonth)
            ->count();

        $submittedThisMonthValue = (clone $proposals)
            ->whereYear('submission_date', $currentYear)
            ->whereMonth('submission_date', $currentMonth)
            ->sum(DB::raw(Currency::usdExpr('proposal_value')));

        $submittedThisYear = (clone $proposals)
            ->whereYear('submission_date', $currentYear)
            ->count();

        $submittedThisYearValue = (clone $proposals)
            ->whereYear('submission_date', $currentYear)
            ->sum(DB::raw(Currency::usdExpr('proposal_value')));

        $activeOpportunities = (clone $opportunities)->active()->count();
        $newOpportunitiesThisMonth = (clone $opportunities)
            ->whereYear('created_at', $currentYear)
            ->whereMonth('created_at', $currentMonth)
            ->count();

        $overdueTasks = Task::where('organization_id', $organizationId)
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->where('due_date', '<', now()->toDateString())
            ->count();

        $overdueFollowUps = FollowUp::where('organization_id', $organizationId)
            ->where('status', 'overdue')
            ->count();

        $upcomingDeadlines = (clone $proposals)
            ->whereIn('status', ['in_progress'])
            ->whereBetween('due_date', [now()->toDateString(), now()->addDays(14)->toDateString()])
            ->orderBy('due_date')
            ->limit(5)
            ->get(['id', 'proposal_number', 'project_name', 'due_date', 'status']);

        $proposalsByStatus = (clone $proposals)
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $monthlyTrend = $this->getMonthlyProposalTrend($organizationId, 12);
        $topUsers = $this->getTopUsersByProposalValue($organizationId, 5);
        $sourceAnalysis = $this->getOpportunitySourceAnalysis($organizationId);

        // Expected revenue this month (Phase 4): open proposals whose expected
        // award date lands this month, weighted by win probability.
        $expectedMonthlyRevenue = (float) (clone $proposals)
            ->whereIn('status', ['in_progress', 'submitted', 'award_pending', 'clarification_requested', 'protested'])
            ->whereNotNull('expected_award_date')
            ->whereYear('expected_award_date', $currentYear)
            ->whereMonth('expected_award_date', $currentMonth)
            ->sum(DB::raw('(' . Currency::usdExpr('proposal_value') . ') * ' . $this->winProbabilityExpr() . ' / 100'));

        // Average award-cycle duration (Phase 4): days from submission to award,
        // for proposals won this year.
        $avgAwardCycleDays = (int) round((float) (clone $proposals)
            ->whereIn('status', $won)
            ->whereNotNull('submission_date')
            ->whereNotNull('award_date')
            ->whereYear('award_date', $currentYear)
            ->avg(DB::raw('DATEDIFF(award_date, submission_date)')) ?: 0);

        return compact(
            'totalProposals', 'awarded', 'lost', 'winRate', 'lossRate',
            'pipelineValue', 'awardValue', 'submittedThisMonth', 'submittedThisMonthValue',
            'submittedThisYear', 'submittedThisYearValue', 'activeOpportunities',
            'newOpportunitiesThisMonth', 'overdueTasks', 'overdueFollowUps',
            'upcomingDeadlines', 'proposalsByStatus', 'monthlyTrend', 'topUsers', 'sourceAnalysis',
            'expectedMonthlyRevenue', 'avgAwardCycleDays'
        );
    }

    public function getUserDashboard(int $organizationId, User $user): array
    {
        $myProposals = ProposalSubmission::forOrganization($organizationId)->where('owner_id', $user->id);

        // Proposals this user can actually see (same scope as the Applications
        // board). The "Active Proposals" card and the deadlines panel use this so
        // their numbers match what the user sees when they open the linked board,
        // instead of silently counting only proposals they personally own.
        $visibleProposals = $this->visibleProposals($organizationId, $user);

        $won = \App\Enums\ProposalStatus::wonValues();

        $mySubmitted = (clone $myProposals)->whereNotNull('submission_date')->count();
        $myAwarded = (clone $myProposals)->whereIn('status', $won)->count();
        $myLost = (clone $myProposals)->where('status', 'lost')->count();
        $myPending = (clone $visibleProposals)->where('status', 'in_progress')->count();

        // Monetary totals are normalised to USD across whatever currency each
        // proposal is denominated in.
        $mySubmittedValue = (clone $myProposals)->whereNotNull('submission_date')->sum(DB::raw(Currency::usdExpr('proposal_value')));
        // Earnings (YTD): value of contracts awarded this calendar year.
        $myAwardValue = (clone $myProposals)->whereIn('status', $won)
            ->whereYear('award_date', now()->year)
            ->sum(DB::raw(Currency::usdExpr('COALESCE(NULLIF(award_value, 0), proposal_value)')));

        // Projected pipeline value across open proposals (includes drafts).
        $openStatuses = ['in_progress', 'submitted', 'award_pending', 'clarification_requested', 'protested'];
        $myPipelineValue = (clone $myProposals)
            ->whereIn('status', $openStatuses)
            ->sum(DB::raw(Currency::usdExpr('proposal_value')));

        // Weighted pipeline (Phase 4 forecasting): each open proposal's USD value
        // × its win probability. When a proposal has no probability set yet, fall
        // back to a sensible per-stage baseline so the forecast is useful from day
        // one and sharpens as owners set explicit probabilities.
        $myWeightedPipelineValue = (clone $myProposals)
            ->whereIn('status', $openStatuses)
            ->sum(DB::raw('(' . Currency::usdExpr('proposal_value') . ') * ' . $this->winProbabilityExpr() . ' / 100'));

        // Org-wide pipeline — what admins see on the pipeline cards instead of
        // their own (an admin/owner typically owns no proposals personally, so the
        // "My" figures would read $0 while the company pipeline is substantial).
        $companyPipelineValue = ProposalSubmission::forOrganization($organizationId)
            ->whereIn('status', $openStatuses)
            ->sum(DB::raw(Currency::usdExpr('proposal_value')));
        $companyWeightedPipelineValue = ProposalSubmission::forOrganization($organizationId)
            ->whereIn('status', $openStatuses)
            ->sum(DB::raw('(' . Currency::usdExpr('proposal_value') . ') * ' . $this->winProbabilityExpr() . ' / 100'));

        $myCommissions = Commission::where('user_id', $user->id)
            ->where('organization_id', $organizationId)
            ->whereYear('created_at', now()->year)
            ->sum('commission_amount');

        $myTasks = Task::where('assigned_to', $user->id)
            ->where('organization_id', $organizationId)
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->count();

        $myFollowUps = FollowUp::where('assigned_to', $user->id)
            ->where('organization_id', $organizationId)
            ->whereIn('status', ['scheduled', 'overdue'])
            ->count();

        $myUpcomingDeadlines = (clone $visibleProposals)
            ->where('status', 'in_progress')
            ->whereBetween('due_date', [now()->toDateString(), now()->addDays(30)->toDateString()])
            ->orderBy('due_date')
            ->get(['id', 'proposal_number', 'project_name', 'due_date', 'status']);

        // Recent additions to the portal (proposals + documents), org-wide.
        $recentProposals = ProposalSubmission::forOrganization($organizationId)
            ->latest()->limit(6)
            ->get(['id', 'proposal_number', 'project_name', 'proposal_value', 'currency', 'created_at']);
        $recentDocuments = \App\Models\ProposalFile::whereHas('proposal', fn ($q) => $q->where('organization_id', $organizationId))
            ->with('proposal:id,proposal_number')
            ->latest()->limit(6)
            ->get(['id', 'proposal_submission_id', 'display_name', 'created_at']);

        $recentActivity = $recentProposals->map(fn ($p) => [
            'type' => 'proposal',
            'title' => $p->project_name,
            'sub' => $p->proposal_number,
            'value' => Currency::toUsd((float) $p->proposal_value, $p->currency),
            'url' => "/proposals/{$p->id}",
            'at' => $p->created_at?->toIso8601String(),
        ])->concat($recentDocuments->map(fn ($f) => [
            'type' => 'document',
            'title' => $f->display_name,
            'sub' => $f->proposal?->proposal_number,
            'value' => 0.0,
            'url' => "/proposals/{$f->proposal_submission_id}",
            'at' => $f->created_at?->toIso8601String(),
        ]))->sortByDesc('at')->take(7)->values()->all();

        // Company-wide totals (visible to all users)
        $companyTotalProposals = ProposalSubmission::forOrganization($organizationId)
            ->whereYear('created_at', now()->year)
            ->count();

        $companyMonthlySubmissions = ProposalSubmission::forOrganization($organizationId)
            ->whereYear('submission_date', now()->year)
            ->whereMonth('submission_date', now()->month)
            ->count();

        $companyMonthlyValue = ProposalSubmission::forOrganization($organizationId)
            ->whereYear('submission_date', now()->year)
            ->whereMonth('submission_date', now()->month)
            ->sum(DB::raw(Currency::usdExpr('proposal_value')));

        // Total value of everything that has been submitted (any submitted-or-later
        // status), shown as its own bubble separate from the overall pipeline total.
        $submittedStatuses = ['submitted', 'award_pending', 'clarification_requested', 'awarded', 'completed', 'lost', 'protested'];
        $companySubmittedValue = ProposalSubmission::forOrganization($organizationId)
            ->whereIn('status', $submittedStatuses)
            ->sum(DB::raw(Currency::usdExpr('proposal_value')));
        $companySubmittedCount = ProposalSubmission::forOrganization($organizationId)
            ->whereIn('status', $submittedStatuses)
            ->count();

        // Org-wide earnings: value of every contract awarded this year, so the
        // dashboard reflects kanban awards regardless of who owns the proposal.
        $companyAwardValue = ProposalSubmission::forOrganization($organizationId)
            ->whereIn('status', $won)
            ->whereYear('award_date', now()->year)
            ->sum(DB::raw(Currency::usdExpr('COALESCE(NULLIF(award_value, 0), proposal_value)')));

        // Admin-only org-wide views (replace the "My …" cards on the main
        // dashboard for admins): total submissions by interval + a count of
        // upcoming submissions whose deadline is approaching.
        $isAdmin = $user->hasRole('Super Admin');
        $orgSubmissions = null;
        $upcomingSubmissions = null;
        if ($isAdmin) {
            $window = function (?int $days) use ($organizationId, $submittedStatuses) {
                $q = ProposalSubmission::forOrganization($organizationId)
                    ->whereIn('status', $submittedStatuses)
                    ->whereNotNull('submission_date');
                if ($days !== null) {
                    $q->whereDate('submission_date', '>=', now()->subDays($days)->toDateString());
                }
                return [
                    'count' => (clone $q)->count(),
                    'value' => (float) (clone $q)->sum(DB::raw(Currency::usdExpr('proposal_value'))),
                ];
            };
            $orgSubmissions = ['last7' => $window(7), 'last30' => $window(30), 'last60' => $window(60), 'total' => $window(null)];

            $dueWithin = fn (int $days) => ProposalSubmission::forOrganization($organizationId)
                ->whereIn('status', ['in_progress', 'submitted', 'award_pending', 'clarification_requested', 'protested'])
                ->whereNotNull('due_date')
                ->whereBetween('due_date', [now()->toDateString(), now()->addDays($days)->toDateString()])
                ->count();
            $upcomingSubmissions = ['this_week' => $dueWithin(7), 'in15' => $dueWithin(15)];
        }

        return compact(
            'mySubmitted', 'myAwarded', 'myLost', 'myPending',
            'mySubmittedValue', 'myAwardValue', 'myPipelineValue', 'myWeightedPipelineValue',
            'companyPipelineValue', 'companyWeightedPipelineValue', 'myCommissions',
            'myTasks', 'myFollowUps', 'myUpcomingDeadlines', 'recentActivity',
            'companyTotalProposals', 'companyMonthlySubmissions', 'companyMonthlyValue',
            'companySubmittedValue', 'companySubmittedCount', 'companyAwardValue',
            'isAdmin', 'orgSubmissions', 'upcomingSubmissions'
        );
    }

    /**
     * SQL expression for a proposal's win probability (0–100): the explicit
     * win_probability when set, else a per-stage baseline so weighted-pipeline
     * forecasts are meaningful before owners fill probabilities in.
     */
    private function winProbabilityExpr(): string
    {
        return "COALESCE(win_probability, CASE status "
            . "WHEN 'in_progress' THEN 25 "
            . "WHEN 'submitted' THEN 50 "
            . "WHEN 'award_pending' THEN 75 "
            . "WHEN 'clarification_requested' THEN 50 "
            . "WHEN 'protested' THEN 40 "
            . "ELSE 0 END)";
    }

    /**
     * Proposals a user may see — mirrors the Applications board: org-scoped, and
     * unless they can view all proposals, limited to ones they created, own,
     * manage, or are a team member of. Returns a fresh builder (clone per use).
     */
    private function visibleProposals(int $organizationId, User $user)
    {
        $query = ProposalSubmission::forOrganization($organizationId);

        if (!$user->can('view all proposals')) {
            $query->where(fn ($q) => $q
                ->where('created_by', $user->id)
                ->orWhere('owner_id', $user->id)
                ->orWhere('proposal_manager_id', $user->id)
                ->orWhereHas('teamMembers', fn ($tm) => $tm->where('user_id', $user->id)));
        }

        return $query;
    }

    private function getMonthlyProposalTrend(int $organizationId, int $months): array
    {
        $data = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $year = $date->year;
            $month = $date->month;

            $submitted = ProposalSubmission::forOrganization($organizationId)
                ->whereYear('submission_date', $year)
                ->whereMonth('submission_date', $month)
                ->count();

            $awarded = ProposalSubmission::forOrganization($organizationId)
                ->whereIn('status', \App\Enums\ProposalStatus::wonValues())
                ->whereYear('award_date', $year)
                ->whereMonth('award_date', $month)
                ->count();

            $data[] = [
                'month' => $date->format('M Y'),
                'submitted' => $submitted,
                'awarded' => $awarded,
            ];
        }
        return $data;
    }

    private function getTopUsersByProposalValue(int $organizationId, int $limit): array
    {
        return ProposalSubmission::forOrganization($organizationId)
            ->select('owner_id', DB::raw('count(*) as total_proposals'), DB::raw('sum(' . Currency::usdExpr('proposal_value') . ') as total_value'), DB::raw('sum(case when status in (\'awarded\', \'completed\') then 1 else 0 end) as won'))
            ->whereNotNull('owner_id')
            ->groupBy('owner_id')
            ->orderByDesc('total_value')
            ->limit($limit)
            ->with('owner:id,name,email')
            ->get()
            ->map(fn($row) => [
                'user' => $row->owner?->name ?? 'Unknown',
                'total_proposals' => $row->total_proposals,
                'total_value' => (float) $row->total_value,
                'won' => $row->won,
            ])
            ->toArray();
    }

    private function getOpportunitySourceAnalysis(int $organizationId): array
    {
        return Opportunity::forOrganization($organizationId)
            ->select('source', DB::raw('count(*) as count'))
            ->groupBy('source')
            ->pluck('count', 'source')
            ->toArray();
    }
}
