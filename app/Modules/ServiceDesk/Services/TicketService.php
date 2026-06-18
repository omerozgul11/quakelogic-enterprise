<?php

namespace App\Modules\ServiceDesk\Services;

use App\Modules\ServiceDesk\Enums\TicketPriority;
use App\Modules\ServiceDesk\Enums\TicketStatus;
use App\Modules\ServiceDesk\Models\Ticket;
use App\Modules\ServiceDesk\Models\TicketComment;

/**
 * Owns the ticket lifecycle. SLA due dates are derived from priority on open
 * (and recomputed if priority changes while open); status timestamps are
 * stamped on transition.
 */
class TicketService
{
    public function __construct(private readonly TicketNumberService $numbers) {}

    public function open(int $organizationId, int $actorId, array $data): Ticket
    {
        $priority = TicketPriority::from($data['priority'] ?? TicketPriority::Normal->value);
        $openedAt = now();

        return Ticket::create([
            'organization_id' => $organizationId,
            'created_by' => $actorId,
            'assigned_to' => $data['assigned_to'] ?? null,
            'company_id' => $data['company_id'] ?? null,
            'contact_id' => $data['contact_id'] ?? null,
            'asset_id' => $data['asset_id'] ?? null,
            'inventory_product_id' => $data['inventory_product_id'] ?? null,
            'number' => $this->numbers->generate($organizationId),
            'subject' => $data['subject'],
            'description' => $data['description'] ?? null,
            'type' => $data['type'] ?? 'support',
            'status' => $data['assigned_to'] ?? false ? TicketStatus::Open : TicketStatus::New,
            'priority' => $priority,
            'channel' => $data['channel'] ?? 'web',
            'serial_number' => $data['serial_number'] ?? null,
            'rma_disposition' => $data['rma_disposition'] ?? null,
            'opened_at' => $openedAt,
            'due_at' => (clone $openedAt)->addHours($priority->slaHours()),
        ]);
    }

    public function comment(Ticket $ticket, int $userId, string $body, bool $internal = false): TicketComment
    {
        $comment = $ticket->comments()->create([
            'organization_id' => $ticket->organization_id,
            'user_id' => $userId,
            'body' => $body,
            'is_internal' => $internal,
        ]);

        // First public reply records the response time and opens a new ticket.
        $changes = [];
        if (! $internal && $ticket->first_responded_at === null) {
            $changes['first_responded_at'] = now();
        }
        if ($ticket->status === TicketStatus::New) {
            $changes['status'] = TicketStatus::Open;
        }
        if ($changes) {
            $ticket->forceFill($changes)->save();
        }

        return $comment;
    }

    public function assign(Ticket $ticket, ?int $userId): Ticket
    {
        $changes = ['assigned_to' => $userId];
        if ($userId && $ticket->status === TicketStatus::New) {
            $changes['status'] = TicketStatus::Open;
        }
        $ticket->forceFill($changes)->save();

        return $ticket;
    }

    public function setPriority(Ticket $ticket, TicketPriority $priority): Ticket
    {
        $changes = ['priority' => $priority];
        // Re-base the SLA due date off the priority while the ticket is open.
        if ($ticket->status->isOpen() && $ticket->opened_at) {
            $changes['due_at'] = (clone $ticket->opened_at)->addHours($priority->slaHours());
        }
        $ticket->forceFill($changes)->save();

        return $ticket;
    }

    public function transition(Ticket $ticket, TicketStatus $status, ?string $resolution = null): Ticket
    {
        $changes = ['status' => $status];

        if ($status === TicketStatus::Resolved) {
            $changes['resolved_at'] = $ticket->resolved_at ?? now();
            if ($resolution !== null) {
                $changes['resolution'] = $resolution;
            }
        }
        if ($status->isTerminal()) {
            $changes['closed_at'] = $ticket->closed_at ?? now();
        }
        // Reopening clears the closed stamp.
        if ($status->isOpen()) {
            $changes['closed_at'] = null;
        }

        $ticket->forceFill($changes)->save();

        return $ticket;
    }
}
