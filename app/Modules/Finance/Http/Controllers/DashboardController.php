<?php

namespace App\Modules\Finance\Http\Controllers;

use App\Enums\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Models\Crm\Invoice;
use App\Models\Crm\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorizeFinance($request);
        $orgId = $request->user()->organization_id;
        $today = now()->toDateString();

        // Receivables = real invoices (not estimates) still owing.
        $owing = fn () => Invoice::where('organization_id', $orgId)->where('kind', 'invoice')
            ->whereNotIn('status', [InvoiceStatus::Paid->value, InvoiceStatus::Void->value, InvoiceStatus::Draft->value]);

        $outstanding = (float) $owing()->sum(DB::raw('total - amount_paid'));
        $overdue = (float) $owing()->whereNotNull('due_date')->whereDate('due_date', '<', $today)->sum(DB::raw('total - amount_paid'));

        $recent = $owing()->with('company:id,name')
            ->orderBy('due_date')->limit(10)->get()
            ->map(fn (Invoice $i) => [
                'id' => $i->id,
                'number' => $i->number,
                'company' => $i->company?->name,
                'total' => (float) $i->total,
                'balance' => round((float) $i->total - (float) $i->amount_paid, 2),
                'currency' => $i->currency,
                'status_label' => $i->status->label(),
                'status_color' => $i->status->color(),
                'due_date' => $i->due_date?->toDateString(),
                'overdue' => $i->due_date && $i->due_date->isPast(),
            ]);

        return Inertia::render('Finance/Dashboard', [
            'stats' => [
                'outstanding' => round($outstanding, 2),
                'overdue' => round($overdue, 2),
                'collected_month' => round((float) Payment::where('organization_id', $orgId)->where('status', 'completed')
                    ->whereDate('paid_at', '>=', now()->startOfMonth()->toDateString())->sum('amount'), 2),
                'open_invoices' => $owing()->count(),
            ],
            'receivables' => $recent,
            'provider' => config('finance.provider', 'fake'),
        ]);
    }

    private function authorizeFinance(Request $request): void
    {
        abort_unless($request->user()->can('view finance'), 403);
    }
}
