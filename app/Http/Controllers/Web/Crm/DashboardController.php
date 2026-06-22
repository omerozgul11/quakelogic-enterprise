<?php

namespace App\Http\Controllers\Web\Crm;

use App\Enums\InvoiceStatus;
use App\Enums\LeadStatus;
use App\Enums\ProjectStatus;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Crm\Invoice;
use App\Models\Crm\Lead;
use App\Models\Crm\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $orgId = $request->user()->organization_id;

        $openLeads = Lead::where('organization_id', $orgId)->open();
        $invoices = Invoice::where('organization_id', $orgId)->where('kind', 'invoice');

        $stats = [
            'clients' => Company::where('organization_id', $orgId)->count(),
            'contacts' => Contact::where('organization_id', $orgId)->count(),
            'open_leads' => (clone $openLeads)->count(),
            'pipeline_value' => (float) (clone $openLeads)->sum('estimated_value'),
            'active_projects' => Project::where('organization_id', $orgId)
                ->whereNotIn('status', [ProjectStatus::Completed->value, ProjectStatus::Cancelled->value])->count(),
            'outstanding_amount' => (float) (clone $invoices)
                ->whereNotIn('status', [InvoiceStatus::Paid->value, InvoiceStatus::Void->value])
                ->sum(DB::raw('total - amount_paid')),
            'overdue_invoices' => (clone $invoices)->where('status', InvoiceStatus::Overdue->value)->count(),
        ];

        // Pipeline breakdown by stage (counts + value), for the dashboard funnel.
        $byStage = Lead::where('organization_id', $orgId)
            ->selectRaw('status, COUNT(*) as count, COALESCE(SUM(estimated_value), 0) as value')
            ->groupBy('status')->get()->keyBy('status');

        $pipeline = collect(LeadStatus::pipeline())->map(fn (LeadStatus $s) => [
            'key' => $s->value,
            'label' => $s->label(),
            'color' => $s->color(),
            'count' => (int) ($byStage[$s->value]->count ?? 0),
            'value' => (float) ($byStage[$s->value]->value ?? 0),
        ])->values();

        $recentLeads = Lead::where('organization_id', $orgId)
            ->with('company:id,name')
            ->latest()->limit(6)->get()
            ->map(fn (Lead $l) => [
                'id' => $l->id,
                'title' => $l->title,
                'company' => $l->company?->name,
                'value' => (float) $l->estimated_value,
                'status_label' => $l->status->label(),
                'status_color' => $l->status->color(),
            ]);

        $recentInvoices = Invoice::where('organization_id', $orgId)
            ->with('company:id,name')
            ->latest()->limit(6)->get()
            ->map(fn (Invoice $i) => [
                'id' => $i->id,
                'number' => $i->number,
                'kind' => $i->kind,
                'company' => $i->company?->name,
                'total' => (float) $i->total,
                'currency' => $i->currency,
                'status_label' => $i->status->label(),
                'status_color' => $i->status->color(),
            ]);

        $projectsDue = Project::where('organization_id', $orgId)
            ->whereNotNull('due_date')
            ->whereNotIn('status', [ProjectStatus::Completed->value, ProjectStatus::Cancelled->value])
            ->orderBy('due_date')->limit(6)->get()
            ->map(fn (Project $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'due_date' => $p->due_date?->toDateString(),
                'progress' => $p->progress,
                'status_label' => $p->status->label(),
                'status_color' => $p->status->color(),
            ]);

        return Inertia::render('Crm/Dashboard', [
            'stats' => $stats,
            'pipeline' => $pipeline,
            'recentLeads' => $recentLeads,
            'recentInvoices' => $recentInvoices,
            'projectsDue' => $projectsDue,
        ]);
    }
}
