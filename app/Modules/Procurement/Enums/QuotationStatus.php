<?php

namespace App\Modules\Procurement\Enums;

/**
 * Quotation (RFQ) lifecycle:
 *   draft → sent → received → accepted
 * with rejected / expired as terminal states. A received (priced) quote can be
 * accepted, which raises a purchase order from it.
 */
enum QuotationStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Received = 'received';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Sent => 'Sent',
            self::Received => 'Received',
            self::Accepted => 'Accepted',
            self::Rejected => 'Rejected',
            self::Expired => 'Expired',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Sent => 'blue',
            self::Received => 'amber',
            self::Accepted => 'green',
            self::Rejected => 'red',
            self::Expired => 'red',
        };
    }

    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::Sent, self::Received], true);
    }

    /** A quote can be accepted (and turned into a PO) once it's been sent/priced. */
    public function canAccept(): bool
    {
        return in_array($this, [self::Sent, self::Received], true);
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
