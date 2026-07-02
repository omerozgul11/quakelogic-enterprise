<?php

namespace App\Modules\Procurement\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * A procurement document (PR / RFQ / PO / Bill) sent to a vendor: a branded
 * covering message with the document PDF attached. Subject, body, and the PDF
 * are supplied by ProcurementDocumentService so one mailable serves every
 * "send to vendor" action.
 */
class ProcurementDocumentMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $subjectLine,
        public string $bodyText,
        public string $pdf,
        public string $pdfFilename,
        public string $orgName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    public function content(): Content
    {
        return new Content(
            view: 'procurement.mail.document',
            with: ['bodyText' => $this->bodyText, 'orgName' => $this->orgName],
        );
    }

    public function attachments(): array
    {
        return [
            \Illuminate\Mail\Mailables\Attachment::fromData(fn () => $this->pdf, $this->pdfFilename)
                ->withMime('application/pdf'),
        ];
    }
}
