<?php

namespace App\Enums;

enum FollowUpStatus: string
{
    case Scheduled = 'scheduled';
    case Sent = 'sent';
    case Responded = 'responded';
    case Overdue = 'overdue';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match($this) {
            self::Scheduled => 'Scheduled',
            self::Sent => 'Sent',
            self::Responded => 'Responded',
            self::Overdue => 'Overdue',
            self::Cancelled => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Scheduled => 'blue',
            self::Sent => 'indigo',
            self::Responded => 'green',
            self::Overdue => 'red',
            self::Cancelled => 'gray',
        };
    }
}
