<?php

namespace App\Modules\Procurement\Enums;

/**
 * Purchase-request lifecycle:
 *   draft → pending_approval → approved → converted
 * with rejected / cancelled as terminal states. Only a draft is editable; only
 * an approved request can be converted to a quotation or a purchase order.
 */
enum PurchaseRequestStatus: string
{
    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
    case Converted = 'converted';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::PendingApproval => 'Pending Approval',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Cancelled => 'Cancelled',
            self::Converted => 'Converted',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::PendingApproval => 'amber',
            self::Approved => 'green',
            self::Rejected => 'red',
            self::Cancelled => 'red',
            self::Converted => 'indigo',
        };
    }

    /** Lines/details may be edited only while the request is a draft. */
    public function isEditable(): bool
    {
        return $this === self::Draft;
    }

    /** An approved request can be turned into a quotation or a purchase order. */
    public function canConvert(): bool
    {
        return in_array($this, [self::Approved, self::Converted], true);
    }

    /** @return array<int,array{value:string,label:string,color:string}> */
    public static function options(): array
    {
        return array_map(fn (self $s) => [
            'value' => $s->value,
            'label' => $s->label(),
            'color' => $s->color(),
        ], self::cases());
    }
}
