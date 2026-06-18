<?php

namespace App\Modules\Manufacturing\Enums;

/**
 * Work-order lifecycle: draft → released → in_progress → completed, with
 * cancelled as a terminal state. Components are consumed and finished goods
 * produced at completion.
 */
enum WorkOrderStatus: string
{
    case Draft = 'draft';
    case Released = 'released';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Released => 'Released',
            self::InProgress => 'In Progress',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Released => 'blue',
            self::InProgress => 'amber',
            self::Completed => 'green',
            self::Cancelled => 'red',
        };
    }

    /** Header/lines editable only while a draft. */
    public function isEditable(): bool
    {
        return $this === self::Draft;
    }

    public function canStart(): bool
    {
        return $this === self::Released;
    }

    /** Can be built/completed (consume components, produce finished goods). */
    public function canComplete(): bool
    {
        return in_array($this, [self::Released, self::InProgress], true);
    }

    public function isOpen(): bool
    {
        return ! in_array($this, [self::Completed, self::Cancelled], true);
    }
}
