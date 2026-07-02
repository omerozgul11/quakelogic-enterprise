<?php

namespace App\Modules\Procurement\Enums;

/**
 * Per-payment approval. A payment recorded as `pending` does not count toward
 * the bill's paid amount until an approver approves it; `approved` payments
 * count immediately (the default when no approval is required).
 */
enum BillPaymentApprovalStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending Approval',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'amber',
            self::Approved => 'green',
            self::Rejected => 'red',
        };
    }

    public function countsAsPaid(): bool
    {
        return $this === self::Approved;
    }
}
