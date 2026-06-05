<?php

namespace App\Enums;

enum TaskStatus: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case Blocked = 'blocked';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match($this) {
            self::Open => 'Open',
            self::InProgress => 'In Progress',
            self::Blocked => 'Blocked',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Open => 'blue',
            self::InProgress => 'yellow',
            self::Blocked => 'red',
            self::Completed => 'green',
            self::Cancelled => 'gray',
        };
    }
}
