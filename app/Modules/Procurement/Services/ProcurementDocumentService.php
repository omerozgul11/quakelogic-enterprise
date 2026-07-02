<?php

namespace App\Modules\Procurement\Services;

use App\Modules\Procurement\Mail\ProcurementDocumentMail;
use App\Modules\Procurement\Models\Bill;
use App\Modules\Procurement\Models\PurchaseOrder;
use App\Modules\Procurement\Models\PurchaseRequest;
use App\Modules\Procurement\Models\Quotation;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Mail;

/**
 * Builds branded PDFs for procurement documents (PR / RFQ / PO / Bill) and
 * emails them to a vendor with a covering message. One normalized view-model
 * feeds a single Blade template so every document reads the same, and the same
 * mail path (with CC/BCC + custom subject/message + the PDF attached) serves
 * every "send to vendor" action.
 */
class ProcurementDocumentService
{
    /** Raw PDF bytes for the given document. */
    public function pdf(Model $model): string
    {
        return Pdf::loadView('pdf.procurement.document', ['doc' => $this->viewModel($model)])
            ->setPaper('letter')
            ->output();
    }

    /** A stream/download filename like "PO-2026-0001.pdf". */
    public function filename(Model $model): string
    {
        return ($model->number ?: class_basename($model).'-'.$model->getKey()).'.pdf';
    }

    /**
     * Send the document to a vendor as a PDF-attached email. $payload keys:
     * to (string, required), cc (array), bcc (array), subject (string),
     * message (string). Throws if $to is empty.
     */
    public function sendEmail(Model $model, array $payload): void
    {
        $to = trim((string) ($payload['to'] ?? ''));
        if ($to === '') {
            throw new \InvalidArgumentException('A recipient email is required.');
        }

        $orgName = $model->organization?->name ?: 'QuakeLogic';
        $subject = trim((string) ($payload['subject'] ?? '')) ?: $this->defaultSubject($model, $orgName);
        $message = (string) ($payload['message'] ?? '') ?: $this->defaultBody($model, $orgName);

        $mailable = new ProcurementDocumentMail(
            subjectLine: $subject,
            bodyText: $message,
            pdf: $this->pdf($model),
            pdfFilename: $this->filename($model),
            orgName: $orgName,
        );

        Mail::to($to)
            ->cc($this->cleanEmails($payload['cc'] ?? []))
            ->bcc($this->cleanEmails($payload['bcc'] ?? []))
            ->send($mailable);
    }

    /**
     * Suggested recipients for the send modal: the supplier's own email plus
     * each of its contacts. Empty for a PR (it has no vendor).
     */
    public function recipientOptions(Model $model): array
    {
        $supplier = method_exists($model, 'supplier') ? $model->supplier()->first() : null;
        if (! $supplier) {
            return [];
        }

        $options = [];
        if ($supplier->email) {
            $options[] = ['email' => $supplier->email, 'label' => $supplier->name.' (main)'];
        }
        foreach ($supplier->contacts()->orderByDesc('is_primary')->orderBy('id')->get() as $c) {
            if ($c->email) {
                $options[] = ['email' => $c->email, 'label' => trim($c->name.($c->title ? ' — '.$c->title : ''))];
            }
        }

        // De-dupe by email, keeping the first (richest) label.
        $seen = [];

        return array_values(array_filter($options, function ($o) use (&$seen) {
            $key = strtolower($o['email']);
            if (isset($seen[$key])) {
                return false;
            }

            return $seen[$key] = true;
        }));
    }

    /**
     * Everything the "Send to vendor" modal needs to prefill itself: suggested
     * recipients, a default recipient, and a default subject/message. The
     * caller adds the route-specific pdf_url / send_url.
     */
    public function sendMeta(Model $model): array
    {
        $orgName = $model->organization?->name ?: 'QuakeLogic';
        $recipients = $this->recipientOptions($model);

        return [
            'recipients' => $recipients,
            'to' => $recipients[0]['email'] ?? '',
            'subject' => $this->defaultSubject($model, $orgName),
            'message' => $this->defaultBody($model, $orgName),
        ];
    }

    public function defaultSubject(Model $model, string $orgName): string
    {
        return $this->kindLabel($model)." {$model->number} — {$orgName}";
    }

    public function defaultBody(Model $model, string $orgName): string
    {
        $kind = strtolower($this->kindLabel($model));
        $lead = $model instanceof Quotation
            ? "Please find our request for quotation {$model->number} attached. Kindly reply with your pricing and lead time."
            : "Please find {$kind} {$model->number} attached.";

        return "Hello,\n\n{$lead}\n\nThank you,\n{$orgName}";
    }

    // ── View-model normalization ────────────────────────────────────────────

    public function viewModel(Model $model): array
    {
        return match (true) {
            $model instanceof PurchaseRequest => $this->purchaseRequestDoc($model),
            $model instanceof Quotation => $this->quotationDoc($model),
            $model instanceof PurchaseOrder => $this->purchaseOrderDoc($model),
            $model instanceof Bill => $this->billDoc($model),
            default => throw new \InvalidArgumentException('Unsupported procurement document: '.$model::class),
        };
    }

    public function kindLabel(Model $model): string
    {
        return match (true) {
            $model instanceof PurchaseRequest => 'Purchase Request',
            $model instanceof Quotation => 'Request for Quotation',
            $model instanceof PurchaseOrder => 'Purchase Order',
            $model instanceof Bill => 'Bill',
            default => 'Document',
        };
    }

    private function purchaseRequestDoc(PurchaseRequest $pr): array
    {
        $pr->loadMissing(['items', 'organization', 'requester', 'creator']);
        $requester = $pr->requester ?: $pr->creator;

        return [
            'kind_label' => 'Purchase Request',
            'number' => $pr->number,
            'org' => $this->org($pr),
            'currency' => $pr->currency,
            'party_label' => 'Requested by',
            'party' => ['name' => $requester?->name ?: '—', 'lines' => array_filter([$pr->department, $requester?->email])],
            'meta' => [
                ['label' => 'Date', 'value' => optional($pr->created_at)->format('M j, Y')],
                ['label' => 'Title', 'value' => $pr->title ?: '—'],
                ['label' => 'Status', 'value' => $pr->status->label()],
            ],
            'show_unit' => true,
            'items' => $this->items($pr->items, 'quantity'),
            'totals' => array_filter([
                ['label' => 'Subtotal', 'value' => $pr->subtotal],
                ((float) $pr->tax_amount) > 0 ? ['label' => 'Tax', 'value' => $pr->tax_amount] : null,
                ['label' => 'Total', 'value' => $pr->total, 'grand' => true],
            ]),
            'notes' => $pr->notes ?: $pr->description,
            'terms' => null,
            'footer_note' => null,
        ];
    }

    private function quotationDoc(Quotation $q): array
    {
        $q->loadMissing(['items', 'organization', 'supplier']);

        return [
            'kind_label' => 'Request for Quotation',
            'number' => $q->number,
            'org' => $this->org($q),
            'currency' => $q->currency,
            'party_label' => 'Vendor',
            'party' => $this->supplierParty($q->supplier),
            'meta' => array_filter([
                ['label' => 'Quote date', 'value' => optional($q->quote_date)->format('M j, Y')],
                $q->expiry_date ? ['label' => 'Valid until', 'value' => $q->expiry_date->format('M j, Y')] : null,
                $q->reference_no ? ['label' => 'Reference', 'value' => $q->reference_no] : null,
                ['label' => 'Status', 'value' => $q->status->label()],
            ]),
            'show_unit' => true,
            'items' => $this->items($q->items, 'quantity'),
            'totals' => array_filter([
                ['label' => 'Subtotal', 'value' => $q->subtotal],
                ((float) $q->tax_amount) > 0 ? ['label' => 'Tax', 'value' => $q->tax_amount] : null,
                ((float) $q->discount_total) > 0 ? ['label' => 'Discount', 'value' => $q->discount_total] : null,
                ['label' => 'Total', 'value' => $q->total, 'grand' => true],
            ]),
            'notes' => null,
            'terms' => null,
            'footer_note' => 'This is a request for quotation, not an order.',
        ];
    }

    private function purchaseOrderDoc(PurchaseOrder $po): array
    {
        $po->loadMissing(['items', 'organization', 'supplier']);

        return [
            'kind_label' => 'Purchase Order',
            'number' => $po->number,
            'org' => $this->org($po),
            'currency' => $po->currency,
            'party_label' => 'Supplier',
            'party' => $this->supplierParty($po->supplier),
            'meta' => array_filter([
                ['label' => 'Order date', 'value' => optional($po->order_date)->format('M j, Y')],
                $po->expected_date ? ['label' => 'Expected', 'value' => $po->expected_date->format('M j, Y')] : null,
                ['label' => 'Status', 'value' => $po->status->label()],
            ]),
            'show_unit' => false,
            'items' => $this->items($po->items, 'quantity_ordered'),
            'totals' => array_filter([
                ['label' => 'Subtotal', 'value' => $po->subtotal],
                ((float) $po->tax_amount) > 0 ? ['label' => 'Tax ('.rtrim(rtrim(number_format((float) $po->tax_rate, 2), '0'), '.').'%)', 'value' => $po->tax_amount] : null,
                ((float) $po->shipping_amount) > 0 ? ['label' => 'Shipping', 'value' => $po->shipping_amount] : null,
                ['label' => 'Total', 'value' => $po->total, 'grand' => true],
            ]),
            'notes' => $po->notes,
            'terms' => null,
            'footer_note' => 'Reference '.$po->number.' on your invoice and shipping documents.',
        ];
    }

    private function billDoc(Bill $bill): array
    {
        $bill->loadMissing(['items', 'organization', 'supplier']);

        return [
            'kind_label' => 'Bill',
            'number' => $bill->number,
            'org' => $this->org($bill),
            'currency' => $bill->currency,
            'party_label' => 'Vendor',
            'party' => $this->supplierParty($bill->supplier),
            'meta' => array_filter([
                ['label' => 'Bill date', 'value' => optional($bill->bill_date)->format('M j, Y')],
                $bill->due_date ? ['label' => 'Due', 'value' => $bill->due_date->format('M j, Y')] : null,
                $bill->vendor_invoice_number ? ['label' => 'Vendor invoice #', 'value' => $bill->vendor_invoice_number] : null,
                ['label' => 'Status', 'value' => $bill->payment_status->label()],
            ]),
            'show_unit' => true,
            'items' => $this->items($bill->items, 'quantity'),
            'totals' => array_filter([
                ['label' => 'Subtotal', 'value' => $bill->subtotal],
                ((float) $bill->tax_amount) > 0 ? ['label' => 'Tax', 'value' => $bill->tax_amount] : null,
                ((float) $bill->shipping_amount) > 0 ? ['label' => 'Shipping', 'value' => $bill->shipping_amount] : null,
                ((float) $bill->discount_total) > 0 ? ['label' => 'Discount', 'value' => $bill->discount_total] : null,
                ['label' => 'Total', 'value' => $bill->total, 'grand' => true],
                ((float) $bill->amount_paid) > 0 ? ['label' => 'Paid', 'value' => $bill->amount_paid] : null,
                ((float) $bill->amount_paid) > 0 ? ['label' => 'Balance due', 'value' => $bill->balanceDue()] : null,
            ]),
            'notes' => $bill->notes,
            'terms' => $bill->terms,
            'footer_note' => null,
        ];
    }

    /** @param \Illuminate\Support\Collection<int,\Illuminate\Database\Eloquent\Model> $items */
    private function items($items, string $qtyField): array
    {
        return $items->sortBy('position')->values()->map(fn ($it) => [
            'description' => $it->description,
            'sku' => $it->sku,
            'unit' => $it->unit ?? null,
            'quantity' => $it->{$qtyField},
            'unit_cost' => $it->unit_cost,
            'line_total' => $it->line_total,
        ])->all();
    }

    private function supplierParty(?Model $supplier): array
    {
        if (! $supplier) {
            return ['name' => '—', 'lines' => []];
        }

        $line2 = trim(implode(' ', array_filter([$supplier->city, $supplier->state, $supplier->postal_code])));

        return [
            'name' => $supplier->name,
            'lines' => array_filter([$supplier->address_line1, $line2, $supplier->country, $supplier->email, $supplier->phone]),
        ];
    }

    private function org(Model $model): array
    {
        return [
            'name' => $model->organization?->name ?: 'QuakeLogic',
            'logo' => $this->logoDataUri(),
        ];
    }

    private function logoDataUri(): ?string
    {
        $path = public_path('quakelogic-logo.png');
        if (! is_file($path)) {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode((string) file_get_contents($path));
    }

    /** @return array<int,string> */
    private function cleanEmails(mixed $emails): array
    {
        if (is_string($emails)) {
            $emails = preg_split('/[,;]+/', $emails) ?: [];
        }

        return array_values(array_filter(array_map(
            fn ($e) => trim((string) $e),
            is_array($emails) ? $emails : []
        ), fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL) !== false));
    }
}
