<?php

namespace App\Modules\Procurement\Enums;

/**
 * Purchase-order lifecycle:
 *   draft → pending_approval → approved → sent → confirmed → partially_received → received/delivered
 * with cancelled/closed as terminal states. Receiving is allowed once a PO is
 * approved, sent or confirmed (and while partially received). The guarded
 * actions follow this order, but a manager can also set any status directly
 * (PurchaseOrderService::setStatus) — a free-form override at any stage.
 */
enum PurchaseOrderStatus: string
{
    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case Sent = 'sent';
    case Confirmed = 'confirmed';
    case PartiallyReceived = 'partially_received';
    case Received = 'received';
    case Delivered = 'delivered';
    case Closed = 'closed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::PendingApproval => 'Pending Approval',
            self::Approved => 'Approved',
            self::Sent => 'Sent',
            self::Confirmed => 'Confirmed',
            self::PartiallyReceived => 'Partially Received',
            self::Received => 'Received',
            self::Delivered => 'Delivered',
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
            self::Confirmed => 'blue',
            self::PartiallyReceived => 'amber',
            self::Received => 'green',
            self::Delivered => 'green',
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
        return in_array($this, [self::Approved, self::Sent, self::Confirmed, self::PartiallyReceived], true);
    }

    /** Still in flight (not finished or cancelled). */
    public function isOpen(): bool
    {
        return ! in_array($this, [self::Received, self::Delivered, self::Closed, self::Cancelled], true);
    }

    public function isCancelled(): bool
    {
        return $this === self::Cancelled;
    }
}
