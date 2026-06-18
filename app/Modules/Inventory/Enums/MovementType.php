<?php

namespace App\Modules\Inventory\Enums;

/**
 * Reasons a stock balance changes. Each carries an intrinsic direction:
 * +1 increases on-hand, −1 decreases it, 0 means the sign is supplied by the
 * caller (adjustments / counts can go either way).
 */
enum MovementType: string
{
    case Receipt = 'receipt';
    case Issue = 'issue';
    case Adjustment = 'adjustment';
    case Count = 'count';
    case TransferIn = 'transfer_in';
    case TransferOut = 'transfer_out';
    case Return = 'return';

    public function label(): string
    {
        return match ($this) {
            self::Receipt => 'Receipt',
            self::Issue => 'Issue',
            self::Adjustment => 'Adjustment',
            self::Count => 'Cycle Count',
            self::TransferIn => 'Transfer In',
            self::TransferOut => 'Transfer Out',
            self::Return => 'Return',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Receipt, self::Return, self::TransferIn => 'green',
            self::Issue, self::TransferOut => 'red',
            self::Adjustment => 'amber',
            self::Count => 'blue',
        };
    }

    /** Intrinsic direction: +1 in, −1 out, 0 = caller-signed. */
    public function direction(): int
    {
        return match ($this) {
            self::Receipt, self::Return, self::TransferIn => 1,
            self::Issue, self::TransferOut => -1,
            self::Adjustment, self::Count => 0,
        };
    }
}
