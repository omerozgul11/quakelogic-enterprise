<?php

namespace App\Enums;

/**
 * Delivery lifecycle for a CRM project. Auto-created projects start at `New`.
 * Only `Completed` and `Cancelled` are terminal; everything else is "open".
 */
enum ProjectStatus: string
{
    case New = 'new';
    case Planning = 'planning';
    case InProgress = 'in_progress';
    case WaitingForClient = 'waiting_client';
    case Procurement = 'procurement';
    case Production = 'production';
    case Installation = 'installation';
    case TestingFat = 'testing_fat';
    case Delivered = 'delivered';
    case Completed = 'completed';
    case OnHold = 'on_hold';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::New => 'New',
            self::Planning => 'Planning',
            self::InProgress => 'In Progress',
            self::WaitingForClient => 'Waiting for Client',
            self::Procurement => 'Procurement',
            self::Production => 'Production',
            self::Installation => 'Installation',
            self::TestingFat => 'Testing / FAT',
            self::Delivered => 'Delivered',
            self::Completed => 'Completed',
            self::OnHold => 'On Hold',
            self::Cancelled => 'Cancelled',
        };
    }

    /** Pill-safe colour (gray|blue|indigo|green|red|amber). */
    public function color(): string
    {
        return match ($this) {
            self::New => 'gray',
            self::Planning => 'blue',
            self::InProgress => 'indigo',
            self::WaitingForClient => 'amber',
            self::Procurement => 'blue',
            self::Production => 'indigo',
            self::Installation => 'indigo',
            self::TestingFat => 'amber',
            self::Delivered => 'green',
            self::Completed => 'green',
            self::OnHold => 'amber',
            self::Cancelled => 'red',
        };
    }

    public function isOpen(): bool
    {
        return ! in_array($this, [self::Completed, self::Cancelled], true);
    }

    public function isComplete(): bool
    {
        return $this === self::Completed;
    }

    public static function default(): self
    {
        return self::New;
    }
}
