<?php

namespace App\Http\Controllers\Web\Crm;

use App\Enums\InvoiceStatus;
use App\Enums\LeadStatus;
use App\Enums\ProjectStatus;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Crm\Activity;
use App\Models\Crm\Invoice;
use App\Models\Crm\Lead;
use App\Models\Crm\Leave;
use App\Models\Crm\Project;
use App\Models\Crm\TimeEntry;
use App\Models\User;
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
            'teamPresence' => $this->teamPresence($request, $orgId),
            'followUps' => FollowUpController::queueFor($request->user()),
            'recentActivity' => $this->recentActivity($orgId),
            'followUpMeta' => [
                'owners' => User::where('organization_id', $orgId)->where('is_active', true)
                    ->orderBy('name')->get(['id', 'name']),
                'currentUserId' => $request->user()->id,
                'priorities' => \App\Models\Crm\FollowUp::PRIORITIES,
            ],
        ]);
    }

    /**
     * The latest timeline entries across the org, with a link back to the record.
     *
     * @return \Illuminate\Support\Collection<int, array<string,mixed>>
     */
    private function recentActivity(int $orgId)
    {
        return Activity::forOrganization($orgId)
            ->with(['user:id,name', 'subject'])
            ->orderByDesc('happened_at')
            ->limit(8)
            ->get()
            ->map(function (Activity $a) {
                $subject = $a->subject;
                $label = null;
                $link = null;
                if ($subject instanceof Lead) {
                    $label = $subject->title;
                    $link = "/crm/leads/{$subject->id}";
                } elseif ($subject instanceof Company) {
                    $label = $subject->name;
                    $link = "/crm/clients/{$subject->id}";
                } elseif ($subject instanceof Contact) {
                    $label = trim("{$subject->first_name} {$subject->last_name}");
                }

                return [
                    'id' => $a->id,
                    'type' => $a->type,
                    'body' => $a->body,
                    'user' => $a->user?->name,
                    'subject_label' => $label,
                    'subject_link' => $link,
                    'happened_at' => $a->happened_at?->toIso8601String(),
                ];
            });
    }

    /**
     * Live attendance snapshot for the whole organization: who's clocked in,
     * clocked out, or on leave right now. Read-only — never touches the live
     * clock-in/out flow. Each active member lands in exactly one bucket
     * (clocked-in wins over on-leave if someone punched in during their leave).
     *
     * @return array<string, mixed>
     */
    private function teamPresence(Request $request, int $orgId): array
    {
        $today = now()->toDateString();

        $members = User::where('organization_id', $orgId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $openByUser = TimeEntry::forOrganization($orgId)
            ->whereNull('clock_out')
            ->orderByDesc('clock_in')
            ->get(['user_id', 'clock_in'])
            ->groupBy('user_id');

        $leaveByUser = Leave::forOrganization($orgId)
            ->coveringDate($today)
            ->orderBy('end_date')
            ->get(['id', 'user_id', 'type', 'end_date'])
            ->keyBy('user_id');

        $rows = $members->map(function (User $u) use ($openByUser, $leaveByUser) {
            $open = $openByUser->get($u->id)?->first();
            $leave = $leaveByUser->get($u->id);
            $status = $open ? 'in' : ($leave ? 'leave' : 'out');

            return [
                'id' => $u->id,
                'name' => $u->name,
                'status' => $status,
                'since' => $open?->clock_in?->format('g:i A'),
                'leave' => $leave ? [
                    'id' => $leave->id,
                    'type' => $leave->type,
                    'until' => $leave->end_date?->format('M j'),
                ] : null,
            ];
        });

        $counts = $rows->countBy(fn (array $r) => $r['status']);

        return [
            'total' => $members->count(),
            'clocked_in' => (int) $counts->get('in', 0),
            'clocked_out' => (int) $counts->get('out', 0),
            'on_leave' => (int) $counts->get('leave', 0),
            'members' => $rows->values(),
            'can_manage' => (bool) $request->user()->can('manage all time cards'),
        ];
    }
}
