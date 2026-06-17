<?php

namespace App\Enums;

enum LeadStatus: string
{
    case New = 'new';
    case Contacted = 'contacted';
    case Qualified = 'qualified';
    case Proposal = 'proposal';
    case Won = 'won';
    case Lost = 'lost';

    public function label(): string
    {
        return match ($this) {
            self::New => 'New',
            self::Contacted => 'Contacted',
            self::Qualified => 'Qualified',
            self::Proposal => 'Proposal',
            self::Won => 'Won',
            self::Lost => 'Lost',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::New => 'gray',
            self::Contacted => 'blue',
            self::Qualified => 'indigo',
            self::Proposal => 'amber',
            self::Won => 'green',
            self::Lost => 'red',
        };
    }

    /** Stages that are still "open" in the pipeline (not won/lost). */
    public function isOpen(): bool
    {
        return ! in_array($this, [self::Won, self::Lost], true);
    }

    /** The ordered pipeline columns for the board view. */
    public static function pipeline(): array
    {
        return [self::New, self::Contacted, self::Qualified, self::Proposal, self::Won, self::Lost];
    }
}
