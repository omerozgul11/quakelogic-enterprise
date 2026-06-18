<?php

namespace App\Modules\ServiceDesk\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\ServiceDesk\Enums\TicketStatus;
use App\Modules\ServiceDesk\Models\Ticket;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Ticket::class);
        $user = $request->user();
        $orgId = $user->organization_id;
        $openStatuses = collect(TicketStatus::cases())->filter(fn ($s) => $s->isOpen())->map->value->all();

        $mine = Ticket::where('organization_id', $orgId)
            ->where('assigned_to', $user->id)
            ->whereIn('status', $openStatuses)
            ->with('assignee:id,name')
            ->orderBy('due_at')->limit(10)->get()
            ->map(fn (Ticket $t) => $this->row($t));

        $unassignedQueue = Ticket::where('organization_id', $orgId)
            ->whereNull('assigned_to')
            ->whereIn('status', $openStatuses)
            ->latest('id')->limit(10)->get()
            ->map(fn (Ticket $t) => $this->row($t));

        return Inertia::render('ServiceDesk/Dashboard', [
            'stats' => [
                'open' => Ticket::where('organization_id', $orgId)->whereIn('status', $openStatuses)->count(),
                'overdue' => Ticket::where('organization_id', $orgId)->whereIn('status', $openStatuses)
                    ->whereNotNull('due_at')->where('due_at', '<', now())->count(),
                'unassigned' => Ticket::where('organization_id', $orgId)->whereNull('assigned_to')->whereIn('status', $openStatuses)->count(),
                'resolved_week' => Ticket::where('organization_id', $orgId)
                    ->where('status', TicketStatus::Resolved->value)->where('resolved_at', '>=', now()->subWeek())->count(),
            ],
            'my_queue' => $mine,
            'unassigned_queue' => $unassignedQueue,
        ]);
    }

    /** @return array<string,mixed> */
    private function row(Ticket $t): array
    {
        return [
            'id' => $t->id,
            'number' => $t->number,
            'subject' => $t->subject,
            'status_label' => $t->status->label(),
            'status_color' => $t->status->color(),
            'priority_label' => $t->priority->label(),
            'priority_color' => $t->priority->color(),
            'overdue' => $t->isOverdue(),
            'due_at' => $t->due_at?->toIso8601String(),
        ];
    }
}
