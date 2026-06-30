<?php

namespace App\Enums\Crm;

/**
 * Lifecycle of a field-execution record — from scheduled through to completed
 * and customer-accepted (signed off), with blocked/cancelled escape hatches.
 */
enum ExecutionRecordStatus: string
{
    case Scheduled = 'scheduled';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Accepted = 'accepted';
    case Blocked = 'blocked';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Scheduled',
            self::InProgress => 'In progress',
            self::Completed => 'Completed',
            self::Accepted => 'Accepted',
            self::Blocked => 'Blocked',
            self::Cancelled => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Scheduled => 'gray',
            self::InProgress => 'amber',
            self::Completed => 'green',
            self::Accepted => 'emerald',
            self::Blocked => 'red',
            self::Cancelled => 'gray',
        };
    }

    /** @return array<int,array{value:string,label:string,color:string}> */
    public static function options(): array
    {
        return array_map(
            fn (self $s) => ['value' => $s->value, 'label' => $s->label(), 'color' => $s->color()],
            self::cases(),
        );
    }
}
