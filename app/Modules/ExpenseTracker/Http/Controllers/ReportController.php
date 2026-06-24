<?php

namespace App\Modules\ExpenseTracker\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\ExpenseTracker\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('view expenses'), 403);
        $orgId = $request->user()->organization_id;
        [$from, $to] = $this->range($request);

        // Each call returns a fresh query scoped to the org + date range. Columns
        // are table-qualified so the by-person / by-category joins below (users
        // and expense_categories also carry organization_id) stay unambiguous.
        $base = fn () => Expense::where('expenses.organization_id', $orgId)->spend()
            ->whereDate('expenses.expense_date', '>=', $from)
            ->whereDate('expenses.expense_date', '<=', $to);

        $byCategory = $base()
            ->leftJoin('expense_categories', 'expenses.expense_category_id', '=', 'expense_categories.id')
            ->groupBy('expense_categories.id', 'expense_categories.name')
            ->select(DB::raw('COALESCE(expense_categories.name, \'Uncategorized\') as name'), DB::raw('SUM(expenses.amount) as total'))
            ->orderByDesc('total')->get()
            ->map(fn ($r) => ['name' => $r->name, 'total' => round((float) $r->total, 2)])->all();

        $byPerson = $base()
            ->join('users', 'expenses.owner_id', '=', 'users.id')
            ->groupBy('users.id', 'users.name')
            ->select('users.name', DB::raw('SUM(expenses.amount) as total'))
            ->orderByDesc('total')->limit(15)->get()
            ->map(fn ($r) => ['name' => $r->name, 'total' => round((float) $r->total, 2)])->all();

        $byVendor = $base()
            ->whereNotNull('vendor')->where('vendor', '!=', '')
            ->groupBy('vendor')
            ->select('vendor', DB::raw('SUM(amount) as total'))
            ->orderByDesc('total')->limit(15)->get()
            ->map(fn ($r) => ['name' => $r->vendor, 'total' => round((float) $r->total, 2)])->all();

        $billable = round((float) $base()->where('is_billable', true)->sum('amount'), 2);
        $nonBillable = round((float) $base()->where('is_billable', false)->sum('amount'), 2);

        return Inertia::render('Expenses/Reports/Index', [
            'filters' => ['from' => $from, 'to' => $to],
            'total' => round((float) $base()->sum('amount'), 2),
            'byCategory' => $byCategory,
            'byPerson' => $byPerson,
            'byVendor' => $byVendor,
            'billableSplit' => ['billable' => $billable, 'non_billable' => $nonBillable],
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()->can('view expenses'), 403);
        $orgId = $request->user()->organization_id;
        [$from, $to] = $this->range($request);

        $rows = Expense::where('organization_id', $orgId)
            ->with(['category:id,name', 'owner:id,name'])
            ->whereDate('expense_date', '>=', $from)
            ->whereDate('expense_date', '<=', $to)
            ->orderBy('expense_date')->get();

        $filename = "expenses_{$from}_to_{$to}.csv";

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Number', 'Date', 'Vendor', 'Description', 'Category', 'Owner', 'Status', 'Billable', 'Currency', 'Amount']);
            foreach ($rows as $e) {
                fputcsv($out, [
                    $e->number,
                    $e->expense_date?->toDateString(),
                    $e->vendor,
                    $e->description,
                    $e->category?->name,
                    $e->owner?->name,
                    $e->status->label(),
                    $e->is_billable ? 'Yes' : 'No',
                    $e->currency,
                    number_format((float) $e->amount, 2, '.', ''),
                ]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /** @return array{0:string,1:string} [from, to] as Y-m-d, default = year-to-date. */
    private function range(Request $request): array
    {
        $from = $request->date('from')?->toDateString() ?? now()->startOfYear()->toDateString();
        $to = $request->date('to')?->toDateString() ?? now()->toDateString();

        return [$from, $to];
    }
}
