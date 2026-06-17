<?php

namespace App\Enums;

/**
 * Phase 5 — Contract lifecycle, picking up where the proposal lifecycle ends
 * (after Awarded): Contract Review → Contract Signed → PO Received → Invoice
 * Sent → Paid. Tracked on a Contract record so the Applications board stays
 * focused on the bid pipeline.
 */
enum ContractStage: string
{
    case ContractReview = 'contract_review';
    case ContractSigned = 'contract_signed';
    case PoReceived = 'po_received';
    case InvoiceSent = 'invoice_sent';
    case Paid = 'paid';

    public function label(): string
    {
        return match ($this) {
            self::ContractReview => 'Contract Review',
            self::ContractSigned => 'Contract Signed',
            self::PoReceived => 'PO Received',
            self::InvoiceSent => 'Invoice Sent',
            self::Paid => 'Paid',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::ContractReview => 'amber',
            self::ContractSigned => 'blue',
            self::PoReceived => 'indigo',
            self::InvoiceSent => 'orange',
            self::Paid => 'green',
        };
    }

    /** Linear order used for the stepper / progress display. */
    public static function ordered(): array
    {
        return [self::ContractReview, self::ContractSigned, self::PoReceived, self::InvoiceSent, self::Paid];
    }

    public function isComplete(): bool
    {
        return $this === self::Paid;
    }
}
