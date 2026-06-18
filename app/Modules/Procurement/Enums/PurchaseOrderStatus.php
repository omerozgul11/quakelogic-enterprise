<?php

namespace App\Modules\Procurement\Enums;

/**
 * Purchase-order lifecycle:
 *   draft → pending_approval → approved → sent → partially_received → received
 * with cancelled/closed as terminal states. Receiving is allowed once a PO is
 * approved or sent (and while partially received).
 */
enum PurchaseOrderStatus: string
{
    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case Sent = 'sent';
    case PartiallyReceived = 'partially_received';
    case Received = 'received';
    case Closed = 'closed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::PendingApproval => 'Pending Approval',
            self::Approved => 'Approved',
            self::Sent => 'Sent',
            self::PartiallyReceived => 'Partially Received',
            self::Received => 'Received',
            self::Closed => 'Closed',
            self::Cancelled => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::PendingApproval => 'amber',
            self::Approved => 'indigo',
            self::Sent => 'blue',
            self::PartiallyReceived => 'amber',
            self::Received => 'green',
            self::Closed => 'green',
            self::Cancelled => 'red',
        };
    }

    /** Lines may be added/edited only while the PO is still a draft. */
    public function isEditable(): bool
    {
        return $this === self::Draft;
    }

    /** Goods can be received against the PO in these states. */
    public function canReceive(): bool
    {
        return in_array($this, [self::Approved, self::Sent, self::PartiallyReceived], true);
    }

    /** Still in flight (not finished or cancelled). */
    public function isOpen(): bool
    {
        return ! in_array($this, [self::Received, self::Closed, self::Cancelled], true);
    }

    public function isCancelled(): bool
    {
        return $this === self::Cancelled;
    }
}
