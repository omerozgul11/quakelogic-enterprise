<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ProposalSubmission;
use App\Models\Opportunity;
use App\Models\Commission;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Commission::class);
        $user = $request->user();
        $orgId = $user->organization_id;

        $proposals = ProposalSubmission::where('organization_id', $orgId)
            ->selectRaw('YEAR(created_at) as year, MONTH(created_at) as month, COUNT(*) as total,
                SUM(CASE WHEN status = "awarded" THEN 1 ELSE 0 END) as awarded,
                SUM(proposal_value) as proposal_value, SUM(award_value) as award_value')
            ->groupByRaw('YEAR(created_at), MONTH(created_at)')
            ->orderByRaw('YEAR(created_at) DESC, MONTH(created_at) DESC')
            ->limit(24)
            ->get();

        $commissions = Commission::where('organization_id', $orgId)
            ->selectRaw('period_month, SUM(commission_amount) as total_commissions, COUNT(*) as count')
            ->groupBy('period_month')
            ->orderByDesc('period_month')
            ->limit(12)
            ->get();

        $topOpportunities = Opportunity::where('organization_id', $orgId)
            ->whereNotNull('award_value')
            ->orderByDesc('award_value')
            ->limit(10)
            ->get(['id', 'title', 'agency_name', 'award_value', 'status', 'due_date']);

        return Inertia::render('Reports/Index', [
            'proposalTrend' => $proposals,
            'commissionTrend' => $commissions,
            'topOpportunities' => $topOpportunities,
        ]);
    }
}
