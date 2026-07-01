<?php

namespace App\Modules\Procurement\Mail;

use App\Modules\Procurement\Models\PurchaseOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The purchase order sent to a supplier when it's marked Sent.
 */
class PurchaseOrderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public PurchaseOrder $purchaseOrder)
    {
        // Eager-load what the template needs (lazy loading is disabled app-wide).
        $this->purchaseOrder->loadMissing(['supplier', 'items', 'organization']);
    }

    public function envelope(): Envelope
    {
        $org = $this->purchaseOrder->organization?->name ?: 'QuakeLogic';

        return new Envelope(subject: "Purchase Order {$this->purchaseOrder->number} — {$org}");
    }

    public function content(): Content
    {
        return new Content(
            view: 'procurement.mail.purchase-order',
            with: ['po' => $this->purchaseOrder],
        );
    }
}
