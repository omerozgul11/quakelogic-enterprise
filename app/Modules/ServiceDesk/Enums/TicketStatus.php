<?php

namespace App\Modules\ServiceDesk\Enums;

/**
 * Ticket lifecycle: new → open → in_progress ⇄ waiting_on_client → resolved →
 * closed, with cancelled as a terminal state.
 */
enum TicketStatus: string
{
    case New = 'new';
    case Open = 'open';
    case InProgress = 'in_progress';
    case WaitingOnClient = 'waiting_on_client';
    case Resolved = 'resolved';
    case Closed = 'closed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::New => 'New',
            self::Open => 'Open',
            self::InProgress => 'In Progress',
            self::WaitingOnClient => 'Waiting on Client',
            self::Resolved => 'Resolved',
            self::Closed => 'Closed',
            self::Cancelled => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::New => 'blue',
            self::Open => 'indigo',
            self::InProgress => 'amber',
            self::WaitingOnClient => 'gray',
            self::Resolved => 'green',
            self::Closed => 'green',
            self::Cancelled => 'red',
        };
    }

    /** Still being worked (counts toward SLA / open queues). */
    public function isOpen(): bool
    {
        return ! in_array($this, [self::Resolved, self::Closed, self::Cancelled], true);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Closed, self::Cancelled], true);
    }
}
