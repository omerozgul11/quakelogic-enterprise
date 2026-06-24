<?php

namespace App\Modules\ExpenseTracker\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\ExpenseTracker\Enums\ExpenseStatus;
use App\Modules\ExpenseTracker\Models\Expense;
use App\Modules\ExpenseTracker\Models\ExpenseCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('view expenses'), 403);
        $orgId = $request->user()->organization_id;

        $spend = fn () => Expense::where('organization_id', $orgId)->spend();

        $startMonth = now()->startOfMonth()->toDateString();
        $startQuarter = now()->firstOfQuarter()->toDateString();
        $startYear = now()->startOfYear()->toDateString();

        return Inertia::render('Expenses/Dashboard', [
            'stats' => [
                'spend_month' => round((float) $spend()->whereDate('expense_date', '>=', $startMonth)->sum('amount'), 2),
                'spend_quarter' => round((float) $spend()->whereDate('expense_date', '>=', $startQuarter)->sum('amount'), 2),
                'spend_year' => round((float) $spend()->whereDate('expense_date', '>=', $startYear)->sum('amount'), 2),
                'pending_approval' => Expense::where('organization_id', $orgId)
                    ->where('status', ExpenseStatus::Submitted->value)->count(),
                'awaiting_reimbursement' => Expense::where('organization_id', $orgId)
                    ->where('status', ExpenseStatus::Approved->value)->count(),
            ],
            'byCategory' => $this->byCategory($orgId),
            'trend' => $this->monthlyTrend($orgId),
            'topVendors' => $this->topVendors($orgId),
            'pending' => $this->pendingQueue($orgId),
        ]);
    }

    /** Current-month spend per category with budget context. @return array<int,mixed> */
    private function byCategory(int $orgId): array
    {
        $start = now()->startOfMonth()->toDateString();

        $totals = Expense::where('organization_id', $orgId)->spend()
            ->whereNotNull('expense_category_id')
            ->whereDate('expense_date', '>=', $start)
            ->groupBy('expense_category_id')
            ->select('expense_category_id', DB::raw('SUM(amount) as total'))
            ->pluck('total', 'expense_category_id');

        return ExpenseCategory::where('organization_id', $orgId)->where('is_active', true)
            ->orderBy('name')->get()
            ->map(function (ExpenseCategory $c) use ($totals) {
                $spent = (float) ($totals[$c->id] ?? 0);
                $budget = $c->budget_amount !== null ? (float) $c->budget_amount : null;

                return [
                    'id' => $c->id,
                    'name' => $c->name,
                    'color' => $c->color,
                    'spent' => round($spent, 2),
                    'budget' => $budget,
                    'over_budget' => $budget !== null && $spent > $budget,
                    'pct' => $budget && $budget > 0 ? min(100, round($spent / $budget * 100)) : null,
                ];
            })
            ->filter(fn ($c) => $c['spent'] > 0 || $c['budget'] !== null)
            ->values()->all();
    }

    /** Total spend per month for the last 12 months (PHP buckets — DB-agnostic). @return array<int,mixed> */
    private function monthlyTrend(int $orgId): array
    {
        $buckets = [];
        for ($i = 11; $i >= 0; $i--) {
            $month = now()->startOfMonth()->subMonths($i);
            $next = $month->copy()->addMonth();
            $total = (float) Expense::where('organization_id', $orgId)->spend()
                ->whereDate('expense_date', '>=', $month->toDateString())
                ->whereDate('expense_date', '<', $next->toDateString())
                ->sum('amount');
            $buckets[] = [
                'month' => $month->format('M'),
                'label' => $month->format('M Y'),
                'total' => round($total, 2),
            ];
        }

        return $buckets;
    }

    /** @return array<int,mixed> */
    private function topVendors(int $orgId): array
    {
        $start = now()->startOfYear()->toDateString();

        return Expense::where('organization_id', $orgId)->spend()
            ->whereNotNull('vendor')->where('vendor', '!=', '')
            ->whereDate('expense_date', '>=', $start)
            ->groupBy('vendor')
            ->select('vendor', DB::raw('SUM(amount) as total'))
            ->orderByDesc('total')->limit(5)->get()
            ->map(fn ($r) => ['vendor' => $r->vendor, 'total' => round((float) $r->total, 2)])
            ->all();
    }

    /** Submitted expenses awaiting a decision. @return array<int,mixed> */
    private function pendingQueue(int $orgId): array
    {
        return Expense::where('organization_id', $orgId)
            ->where('status', ExpenseStatus::Submitted->value)
            ->with(['owner:id,name', 'category:id,name'])
            ->orderBy('submitted_at')->limit(8)->get()
            ->map(fn (Expense $e) => [
                'id' => $e->id,
                'number' => $e->number,
                'vendor' => $e->vendor,
                'amount' => (float) $e->amount,
                'currency' => $e->currency,
                'owner' => $e->owner?->name,
                'category' => $e->category?->name,
                'submitted_at' => $e->submitted_at?->toIso8601String(),
            ])->all();
    }
}
