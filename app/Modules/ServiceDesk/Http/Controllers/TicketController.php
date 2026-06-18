<?php

namespace App\Modules\ServiceDesk\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Contact;
use App\Models\User;
use App\Modules\AssetManagement\Models\Asset;
use App\Modules\Inventory\Models\Product;
use App\Modules\ServiceDesk\Enums\TicketPriority;
use App\Modules\ServiceDesk\Enums\TicketStatus;
use App\Modules\ServiceDesk\Enums\TicketType;
use App\Modules\ServiceDesk\Http\Requests\TicketRequest;
use App\Modules\ServiceDesk\Models\Ticket;
use App\Modules\ServiceDesk\Models\TicketComment;
use App\Modules\ServiceDesk\Services\TicketService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Inertia\Inertia;
use Inertia\Response;

class TicketController extends Controller
{
    public function __construct(private readonly TicketService $service) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Ticket::class);
        $orgId = $request->user()->organization_id;

        $tickets = Ticket::where('organization_id', $orgId)
            ->with(['assignee:id,name', 'company:id,name'])
            ->when($request->search, fn ($q, $s) => $q->where(fn ($w) => $w
                ->where('number', 'like', "%{$s}%")->orWhere('subject', 'like', "%{$s}%")))
            ->when($request->status, fn ($q, $st) => $q->where('status', $st))
            ->when($request->type, fn ($q, $t) => $q->where('type', $t))
            ->when($request->priority, fn ($q, $p) => $q->where('priority', $p))
            ->when($request->assignee === 'me', fn ($q) => $q->where('assigned_to', $request->user()->id))
            ->when($request->assignee === 'unassigned', fn ($q) => $q->whereNull('assigned_to'))
            ->orderByRaw("FIELD(status, 'new','open','in_progress','waiting_on_client','resolved','closed','cancelled')")
            ->orderBy('due_at')
            ->paginate(20)->withQueryString()
            ->through(fn (Ticket $t) => [
                'id' => $t->id,
                'number' => $t->number,
                'subject' => $t->subject,
                'type_label' => $t->type->label(),
                'status' => $t->status->value,
                'status_label' => $t->status->label(),
                'status_color' => $t->status->color(),
                'priority_label' => $t->priority->label(),
                'priority_color' => $t->priority->color(),
                'assignee' => $t->assignee?->name,
                'company' => $t->company?->name,
                'overdue' => $t->isOverdue(),
                'due_at' => $t->due_at?->toIso8601String(),
            ]);

        return Inertia::render('ServiceDesk/Tickets/Index', [
            'tickets' => $tickets,
            'filters' => $request->only(['search', 'status', 'type', 'priority', 'assignee']),
            'types' => TicketType::options(),
            'statuses' => collect(TicketStatus::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()]),
            'priorities' => TicketPriority::options(),
            'can' => ['manage' => $request->user()->can('manage tickets')],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Ticket::class);

        return Inertia::render('ServiceDesk/Tickets/Create', [
            'form' => $this->formData($request),
            'types' => TicketType::options(),
            'priorities' => TicketPriority::options(),
        ]);
    }

    public function store(TicketRequest $request): RedirectResponse
    {
        $this->authorize('create', Ticket::class);
        $user = $request->user();

        $ticket = $this->service->open($user->organization_id, $user->id, $request->validated());

        return redirect()->route('tickets.queue.show', $ticket)->with('success', "Ticket {$ticket->number} created.");
    }

    public function show(Request $request, Ticket $ticket): Response
    {
        $this->authorize('view', $ticket);

        $ticket->load(['assignee:id,name', 'company:id,name', 'contact:id,first_name,last_name', 'asset:id,asset_tag,name', 'product:id,sku,name', 'creator:id,name']);
        $comments = $ticket->comments()->with('author:id,name')->orderBy('id')->get()
            ->map(fn (TicketComment $c) => [
                'id' => $c->id,
                'body' => $c->body,
                'is_internal' => $c->is_internal,
                'author' => $c->author?->name,
                'created_at' => $c->created_at?->toIso8601String(),
            ]);

        return Inertia::render('ServiceDesk/Tickets/Show', [
            'ticket' => [
                'id' => $ticket->id,
                'number' => $ticket->number,
                'subject' => $ticket->subject,
                'description' => $ticket->description,
                'type' => $ticket->type->value,
                'type_label' => $ticket->type->label(),
                'type_color' => $ticket->type->color(),
                'status' => $ticket->status->value,
                'status_label' => $ticket->status->label(),
                'status_color' => $ticket->status->color(),
                'priority' => $ticket->priority->value,
                'priority_label' => $ticket->priority->label(),
                'priority_color' => $ticket->priority->color(),
                'channel' => $ticket->channel,
                'serial_number' => $ticket->serial_number,
                'rma_disposition' => $ticket->rma_disposition,
                'overdue' => $ticket->isOverdue(),
                'due_at' => $ticket->due_at?->toIso8601String(),
                'opened_at' => $ticket->opened_at?->toIso8601String(),
                'resolved_at' => $ticket->resolved_at?->toIso8601String(),
                'resolution' => $ticket->resolution,
                'assignee_id' => $ticket->assigned_to,
                'assignee' => $ticket->assignee?->name,
                'company' => $ticket->company?->name,
                'contact' => $ticket->contact ? trim($ticket->contact->first_name.' '.$ticket->contact->last_name) : null,
                'asset' => $ticket->asset ? ['id' => $ticket->asset->id, 'asset_tag' => $ticket->asset->asset_tag, 'name' => $ticket->asset->name] : null,
                'product' => $ticket->product ? ['id' => $ticket->product->id, 'sku' => $ticket->product->sku] : null,
            ],
            'comments' => $comments,
            'statuses' => collect(TicketStatus::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()]),
            'priorities' => TicketPriority::options(),
            'users' => User::where('organization_id', $ticket->organization_id)->orderBy('name')->get(['id', 'name']),
            'can' => [
                'manage' => $request->user()->can('manage tickets'),
                'comment' => $request->user()->can('comment tickets'),
            ],
        ]);
    }

    public function update(TicketRequest $request, Ticket $ticket): RedirectResponse
    {
        $this->authorize('update', $ticket);
        $ticket->update($request->validated());

        return back()->with('success', 'Ticket updated.');
    }

    public function destroy(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->authorize('delete', $ticket);
        $number = $ticket->number;
        $ticket->delete();

        return redirect()->route('tickets.queue.index')->with('success', "Ticket {$number} deleted.");
    }

    public function comment(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->authorize('comment', $ticket);
        $validated = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'is_internal' => ['boolean'],
        ]);

        $this->service->comment($ticket, $request->user()->id, $validated['body'], (bool) ($validated['is_internal'] ?? false));

        return back()->with('success', 'Comment added.');
    }

    public function assign(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->authorize('update', $ticket);
        $validated = $request->validate([
            'assigned_to' => ['nullable', Rule::exists('users', 'id')->where('organization_id', $ticket->organization_id)],
        ]);

        $this->service->assign($ticket, $validated['assigned_to'] ?? null);

        return back()->with('success', 'Assignment updated.');
    }

    public function status(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->authorize('update', $ticket);
        $validated = $request->validate([
            'status' => ['required', new Enum(TicketStatus::class)],
            'resolution' => ['nullable', 'string', 'max:5000'],
        ]);

        $this->service->transition($ticket, TicketStatus::from($validated['status']), $validated['resolution'] ?? null);

        return back()->with('success', 'Status updated to '.$ticket->status->label().'.');
    }

    public function priority(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->authorize('update', $ticket);
        $validated = $request->validate(['priority' => ['required', new Enum(TicketPriority::class)]]);

        $this->service->setPriority($ticket, TicketPriority::from($validated['priority']));

        return back()->with('success', 'Priority updated.');
    }

    /** @return array<string,mixed> */
    private function formData(Request $request): array
    {
        $orgId = $request->user()->organization_id;

        return [
            'companies' => Company::where('organization_id', $orgId)->orderBy('name')->get(['id', 'name']),
            'contacts' => Contact::where('organization_id', $orgId)->orderBy('last_name')->get(['id', 'first_name', 'last_name', 'company_id']),
            'assets' => Asset::where('organization_id', $orgId)->orderBy('asset_tag')->get(['id', 'asset_tag', 'name']),
            'products' => Product::where('organization_id', $orgId)->where('is_active', true)->orderBy('name')->get(['id', 'sku', 'name']),
            'users' => User::where('organization_id', $orgId)->orderBy('name')->get(['id', 'name']),
        ];
    }
}
