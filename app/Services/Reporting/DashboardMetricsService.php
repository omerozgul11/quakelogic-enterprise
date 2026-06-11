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
            ->whereIn('status', ['draft', 'in_progress', 'under_review', 'submitted', 'pending', 'negotiation'])
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
            ->whereIn('status', ['draft', 'in_progress', 'under_review'])
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

        return compact(
            'totalProposals', 'awarded', 'lost', 'winRate', 'lossRate',
            'pipelineValue', 'awardValue', 'submittedThisMonth', 'submittedThisMonthValue',
            'submittedThisYear', 'submittedThisYearValue', 'activeOpportunities',
            'newOpportunitiesThisMonth', 'overdueTasks', 'overdueFollowUps',
            'upcomingDeadlines', 'proposalsByStatus', 'monthlyTrend', 'topUsers', 'sourceAnalysis'
        );
    }

    public function getUserDashboard(int $organizationId, User $user): array
    {
        $myProposals = ProposalSubmission::forOrganization($organizationId)->where('owner_id', $user->id);

        $won = \App\Enums\ProposalStatus::wonValues();

        $mySubmitted = (clone $myProposals)->whereNotNull('submission_date')->count();
        $myAwarded = (clone $myProposals)->whereIn('status', $won)->count();
        $myLost = (clone $myProposals)->where('status', 'lost')->count();
        $myPending = (clone $myProposals)->whereIn('status', ['draft', 'in_progress', 'under_review'])->count();

        // Monetary totals are normalised to USD across whatever currency each
        // proposal is denominated in.
        $mySubmittedValue = (clone $myProposals)->whereNotNull('submission_date')->sum(DB::raw(Currency::usdExpr('proposal_value')));
        // Earnings (YTD): value of contracts awarded this calendar year.
        $myAwardValue = (clone $myProposals)->whereIn('status', $won)
            ->whereYear('award_date', now()->year)
            ->sum(DB::raw(Currency::usdExpr('COALESCE(NULLIF(award_value, 0), proposal_value)')));

        // Projected pipeline value across open proposals (includes drafts).
        $myPipelineValue = (clone $myProposals)
            ->whereIn('status', ['draft', 'in_progress', 'under_review', 'submitted', 'pending', 'negotiation'])
            ->sum(DB::raw(Currency::usdExpr('proposal_value')));

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

        $myUpcomingDeadlines = (clone $myProposals)
            ->whereIn('status', ['draft', 'in_progress', 'under_review'])
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
        $submittedStatuses = ['submitted', 'pending', 'clarification_requested', 'negotiation', 'awarded', 'completed', 'lost'];
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

        return compact(
            'mySubmitted', 'myAwarded', 'myLost', 'myPending',
            'mySubmittedValue', 'myAwardValue', 'myPipelineValue', 'myCommissions',
            'myTasks', 'myFollowUps', 'myUpcomingDeadlines', 'recentActivity',
            'companyTotalProposals', 'companyMonthlySubmissions', 'companyMonthlyValue',
            'companySubmittedValue', 'companySubmittedCount', 'companyAwardValue'
        );
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
