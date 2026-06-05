<?php

namespace App\Services\Commissions;

use App\Models\Commission;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CommissionReportService
{
    public function getSummaryByUser(int $organizationId, string $periodMonth): array
    {
        return Commission::where('organization_id', $organizationId)
            ->where('period_month', $periodMonth)
            ->selectRaw('user_id, SUM(commission_amount) as total, COUNT(*) as count, SUM(base_amount) as total_base')
            ->with('user:id,name')
            ->groupBy('user_id')
            ->get()
            ->map(fn($row) => [
                'user' => $row->user?->name,
                'user_id' => $row->user_id,
                'total_commission' => (float) $row->total,
                'count' => $row->count,
                'total_base' => (float) $row->total_base,
            ])
            ->toArray();
    }

    public function getYearToDate(int $organizationId, int $year): array
    {
        return Commission::where('organization_id', $organizationId)
            ->where('period_month', 'like', "{$year}-%")
            ->selectRaw('period_month, SUM(commission_amount) as total, COUNT(*) as count')
            ->groupBy('period_month')
            ->orderBy('period_month')
            ->get()
            ->toArray();
    }

    public function getUserAnnualReport(User $user, int $year): array
    {
        $commissions = Commission::where('organization_id', $user->organization_id)
            ->where('user_id', $user->id)
            ->where('period_month', 'like', "{$year}-%")
            ->with('proposal:id,proposal_number,project_name,award_value')
            ->get();

        return [
            'user' => $user->name,
            'year' => $year,
            'total' => $commissions->sum('commission_amount'),
            'count' => $commissions->count(),
            'commissions' => $commissions->toArray(),
        ];
    }
}
