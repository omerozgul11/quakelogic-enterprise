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

        return Inertia::render('Reports/Index', $this->generalReportData($request->user()->organization_id));
    }

    /** Export the general report as pdf, docx, xlsx, or csv. */
    public function indexExport(Request $request, string $format, \App\Services\Reporting\ReportDownloadService $downloads)
    {
        $this->authorize('viewAny', Commission::class);
        abort_unless(in_array($format, ['pdf', 'docx', 'xlsx', 'csv'], true), 404);
        $user = $request->user();
        $data = $this->generalReportData($user->organization_id);
        $org = $user->organization?->name ?? 'QuakeLogic';
        $filename = 'reports-' . now()->format('Y-m-d');

        if ($format === 'pdf') {
            return \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.general-report', [
                ...$data,
                'organization' => $org,
                'generatedBy' => $user->name,
                'generatedAt' => now(),
            ])->download("{$filename}.pdf");
        }

        $fmt = fn ($v) => '$' . number_format((float) $v, 0);
        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

        $sections = [
            [
                'title' => 'Proposal Activity (12 Months)',
                'headers' => ['Month', 'Proposals', 'Awarded', 'Proposal Value', 'Award Value'],
                'rows' => collect($data['proposalTrend'])->take(12)->reverse()->values()->map(fn ($r) => [
                    $months[$r['month'] - 1] . ' ' . $r['year'], $r['total'], $r['awarded'],
                    $fmt($r['proposal_value'] ?? 0), $fmt($r['award_value'] ?? 0),
                ])->all(),
            ],
            [
                'title' => 'Commission Trend',
                'headers' => ['Period', 'Commissions', 'Total'],
                'rows' => collect($data['commissionTrend'])->reverse()->values()->map(fn ($r) => [
                    $r['period_month'], $r['count'], $fmt($r['total_commissions']),
                ])->all(),
            ],
            [
                'title' => 'Top Contracts by Value',
                'headers' => ['#', 'Contract', 'Agency', 'Value'],
                'rows' => collect($data['topOpportunities'])->values()->map(fn ($o, $i) => [
                    $i + 1, $o['title'], $o['agency_name'] ?? '—', $fmt($o['estimated_value']),
                ])->all(),
            ],
        ];

        return $downloads->download($format, $filename, "{$org} — Reports & Analytics",
            'Generated ' . now()->format('F j, Y g:i A') . ' by ' . $user->name, $sections);
    }

    private function generalReportData(int $orgId): array
    {
        $proposals = ProposalSubmission::where('organization_id', $orgId)
            ->selectRaw('YEAR(created_at) as year, MONTH(created_at) as month, COUNT(*) as total,
                SUM(CASE WHEN status IN ("awarded", "completed") THEN 1 ELSE 0 END) as awarded,
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
            ->whereNotNull('estimated_value')
            ->orderByDesc('estimated_value')
            ->limit(10)
            ->get(['id', 'title', 'agency_name', 'estimated_value', 'status', 'due_date']);

        return [
            'proposalTrend' => $proposals,
            'commissionTrend' => $commissions,
            'topOpportunities' => $topOpportunities,
        ];
    }

    /**
     * Team performance report: per-user proposal output, current proposal
     * statuses, and earnings, over a selectable period.
     */
    public function users(Request $request): Response
    {
        abort_unless($request->user()->can('view dashboards'), 403);

        return Inertia::render('Reports/Users', $this->teamPerformanceData($request));
    }

    /** Export the team performance report as pdf, docx, xlsx, or csv. */
    public function usersExport(Request $request, string $format, \App\Services\Reporting\ReportDownloadService $downloads)
    {
        abort_unless($request->user()->can('view dashboards'), 403);
        abort_unless(in_array($format, ['pdf', 'docx', 'xlsx', 'csv'], true), 404);
        $user = $request->user();
        $data = $this->teamPerformanceData($request);
        $org = $user->organization?->name ?? 'QuakeLogic';
        $rangeSlug = $data['period'] === 'custom' ? "{$data['from']}_{$data['to']}" : $data['period'];
        $filename = 'team-performance-' . $rangeSlug . '-' . now()->format('Y-m-d');

        if ($format === 'pdf') {
            return \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.team-performance', [
                ...$data,
                'organization' => $org,
                'generatedBy' => $user->name,
                'generatedAt' => now(),
            ])->download("{$filename}.pdf");
        }

        $fmt = fn ($v) => '$' . number_format((float) $v, 0);

        $sections = [
            [
                'title' => 'Summary',
                'headers' => ['Metric', 'Value'],
                'rows' => [
                    ['Proposals Created', $data['totals']['created']],
                    ['Proposals Submitted', $data['totals']['submitted']],
                    ['Contracts Won', $data['totals']['awarded']],
                    ['Submitted Value', $fmt($data['totals']['submitted_value'])],
                    ['Earnings', $fmt($data['totals']['earnings'])],
                    ['Open Pipeline', $fmt($data['totals']['pipeline_value'])],
                ],
            ],
            [
                'title' => 'Performance by User',
                'headers' => ['User', 'Role', 'Created', 'Submitted', 'Won', 'Lost', 'Win Rate', 'Submitted $', 'Earnings', 'Pipeline $'],
                'rows' => collect($data['team'])->map(fn ($r) => [
                    $r['user'] . ($r['is_active'] ? '' : ' (inactive)'),
                    $r['role'] ?? '—', $r['created'], $r['submitted'], $r['awarded'], $r['lost'],
                    $r['win_rate'] !== null ? $r['win_rate'] . '%' : '—',
                    $fmt($r['submitted_value']), $fmt($r['earnings']), $fmt($r['pipeline_value']),
                ])->all(),
            ],
            [
                'title' => 'Current Proposals by Status',
                'headers' => ['Status', 'Proposals', 'Total Value'],
                'rows' => collect($data['statusBreakdown'])->map(fn ($s) => [
                    $s['label'], $s['count'], $s['value'] > 0 ? $fmt($s['value']) : '—',
                ])->all(),
            ],
        ];

        return $downloads->download($format, $filename,
            "{$org} — Team Performance ({$data['periodLabel']})",
            'Generated ' . now()->format('F j, Y g:i A') . ' by ' . $user->name, $sections);
    }

    private function teamPerformanceData(Request $request): array
    {
        $orgId = $request->user()->organization_id;

        // Custom from/to range takes precedence over the preset periods.
        [$period, $start, $end, $from, $to] = $this->resolveRange($request);

        $won = \App\Enums\ProposalStatus::wonValues();
        $openStatuses = ['in_progress', 'submitted', 'pending', 'clarification_requested'];

        $by = function ($query, string $col, string $agg = 'count(*)') use ($orgId) {
            return $query->where('organization_id', $orgId)
                ->whereNotNull($col)
                ->selectRaw("{$col} as k, {$agg} as v")
                ->groupBy($col)
                ->pluck('v', 'k')
                ->toArray();
        };
        $since = fn ($query, string $dateCol) => $query
            ->when($start, fn ($q) => $q->where($dateCol, '>=', $start))
            ->when($end, fn ($q) => $q->where($dateCol, '<=', $end));

        $created = $by($since(ProposalSubmission::query(), 'created_at'), 'created_by');
        $submitted = $by($since(ProposalSubmission::whereNotNull('submission_date'), 'submission_date'), 'owner_id');
        $awarded = $by($since(ProposalSubmission::whereIn('status', $won)->whereNotNull('award_date'), 'award_date'), 'owner_id');
        $lost = $by($since(ProposalSubmission::where('status', 'lost'), 'updated_at'), 'owner_id');
        $submittedValue = $by($since(ProposalSubmission::whereNotNull('submission_date'), 'submission_date'), 'owner_id', 'COALESCE(SUM(proposal_value), 0)');
        $earnings = $by(
            $since(ProposalSubmission::whereIn('status', $won)->whereNotNull('award_date'), 'award_date'),
            'owner_id',
            'COALESCE(SUM(COALESCE(NULLIF(award_value, 0), proposal_value)), 0)'
        );
        // Pipeline value, active project count, and cancelled count are all
        // "right now" numbers (not period-scoped).
        $pipelineValue = $by(ProposalSubmission::whereIn('status', $openStatuses), 'owner_id', 'COALESCE(SUM(proposal_value), 0)');
        $activeCount = $by(ProposalSubmission::whereIn('status', $openStatuses), 'owner_id');
        $cancelledCount = $by(ProposalSubmission::where('status', 'cancelled'), 'owner_id');

        $team = \App\Models\User::where('organization_id', $orgId)
            ->with('roles:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'is_active'])
            ->map(function ($u) use ($created, $submitted, $awarded, $lost, $submittedValue, $earnings, $pipelineValue, $activeCount, $cancelledCount) {
                $aw = (int) ($awarded[$u->id] ?? 0);
                $lo = (int) ($lost[$u->id] ?? 0);
                return [
                    'user' => $u->name,
                    'role' => $u->roles->first()?->name,
                    'is_active' => (bool) $u->is_active,
                    'created' => (int) ($created[$u->id] ?? 0),
                    'submitted' => (int) ($submitted[$u->id] ?? 0),
                    'awarded' => $aw,
                    'lost' => $lo,
                    'active' => (int) ($activeCount[$u->id] ?? 0),
                    'cancelled' => (int) ($cancelledCount[$u->id] ?? 0),
                    'win_rate' => ($aw + $lo) > 0 ? round($aw / ($aw + $lo) * 100, 1) : null,
                    'submitted_value' => (float) ($submittedValue[$u->id] ?? 0),
                    'earnings' => (float) ($earnings[$u->id] ?? 0),
                    'pipeline_value' => (float) ($pipelineValue[$u->id] ?? 0),
                ];
            })
            ->sortByDesc(fn ($r) => [$r['earnings'], $r['submitted_value'], $r['created']])
            ->values();

        // Current proposal pipeline by status (org-wide snapshot).
        $statusCounts = ProposalSubmission::where('organization_id', $orgId)
            ->selectRaw('status, COUNT(*) as c, COALESCE(SUM(proposal_value), 0) as v')
            ->groupBy('status')
            ->get()
            ->keyBy('status');
        $statusBreakdown = collect(\App\Enums\ProposalStatus::cases())->map(fn ($s) => [
            'status' => $s->value,
            'label' => $s->label(),
            'color' => $s->color(),
            'count' => (int) ($statusCounts[$s->value]->c ?? 0),
            'value' => (float) ($statusCounts[$s->value]->v ?? 0),
        ])->filter(fn ($row) => $row['count'] > 0)->values();

        return [
            'team' => $team,
            'period' => $period,
            'from' => $from,
            'to' => $to,
            'periodLabel' => $this->rangeLabel($period, $start, $end),
            'statusBreakdown' => $statusBreakdown,
            'totals' => [
                'created' => array_sum($created),
                'submitted' => array_sum($submitted),
                'awarded' => array_sum($awarded),
                'submitted_value' => (float) array_sum($submittedValue),
                'earnings' => (float) array_sum($earnings),
                'pipeline_value' => (float) array_sum($pipelineValue),
            ],
        ];
    }

    /**
     * Resolve the reporting window: an explicit from/to pair wins; otherwise
     * one of the preset periods. Returns [period, start, end, from, to] where
     * from/to are the raw Y-m-d strings (null unless custom).
     *
     * @return array{0: string, 1: ?\Illuminate\Support\Carbon, 2: ?\Illuminate\Support\Carbon, 3: ?string, 4: ?string}
     */
    private function resolveRange(Request $request): array
    {
        $parse = function (?string $value): ?\Illuminate\Support\Carbon {
            try {
                return $value ? \Illuminate\Support\Carbon::createFromFormat('Y-m-d', $value) : null;
            } catch (\Throwable) {
                return null;
            }
        };

        $from = $parse($request->input('from'));
        $to = $parse($request->input('to'));

        if ($from && $to) {
            if ($from->gt($to)) {
                [$from, $to] = [$to, $from];
            }
            return ['custom', $from->copy()->startOfDay(), $to->copy()->endOfDay(), $from->toDateString(), $to->toDateString()];
        }

        $period = in_array($request->input('period'), ['month', 'quarter', 'year', 'all'], true)
            ? $request->input('period') : 'year';
        $start = match ($period) {
            'month' => now()->startOfMonth(),
            'quarter' => now()->startOfQuarter(),
            'year' => now()->startOfYear(),
            default => null,
        };

        return [$period, $start, null, null, null];
    }

    private function rangeLabel(string $period, ?\Illuminate\Support\Carbon $start, ?\Illuminate\Support\Carbon $end): string
    {
        if ($period === 'custom' && $start && $end) {
            return $start->format('M j, Y') . ' – ' . $end->format('M j, Y');
        }

        return ['month' => 'This Month', 'quarter' => 'This Quarter', 'year' => 'This Year', 'all' => 'All Time'][$period] ?? $period;
    }
}
