import { useState } from 'react';
import { router } from '@inertiajs/react';
import { Card } from '@/Components/ui/Card';
import { Button } from '@/Components/ui/Button';
import { Select } from '@/Components/ui/Select';
import { Copy } from 'lucide-react';

export interface SourceInvoice {
    id: number;
    number: string;
    kind: string;
    company: string | null;
    total: number;
    currency: string;
    items?: { description: string; quantity: number; unit_cost: number }[];
}

interface Props {
    invoices: SourceInvoice[];
    /** Which document to raise from the invoice. */
    target: 'purchase-requests' | 'purchase-orders';
    /** Vendors — required when target is purchase-orders (a PO needs a supplier). */
    suppliers?: { id: number; name: string }[];
    /**
     * When provided, picking an invoice fills the parent form in-place (client
     * side) instead of posting a server-side "copy to draft". Used on the PO
     * create form so the buyer can review/adjust before saving.
     */
    onApply?: (invoice: SourceInvoice) => void;
}

/**
 * "Copy from a sales invoice/estimate" — starts a draft purchase request or
 * purchase order from an existing CRM sales document, copying its line items.
 * Posts straight to the from-invoice endpoint, which creates the draft and
 * opens it. The CRM document is read-only.
 */
export function CopyFromInvoice({ invoices, target, suppliers, onApply }: Props) {
    const [invoiceId, setInvoiceId] = useState('');
    const [supplierId, setSupplierId] = useState('');
    const [processing, setProcessing] = useState(false);

    if (invoices.length === 0) return null;

    const needsSupplier = target === 'purchase-orders';
    const ready = invoiceId !== '' && (!needsSupplier || supplierId !== '');

    const invoiceOpts = invoices.map(inv => ({
        value: String(inv.id),
        label: `${inv.number} · ${inv.kind}${inv.company ? ` · ${inv.company}` : ''} · ${inv.currency} ${inv.total.toFixed(2)}`,
    }));

    // In-place prefill mode (PO create): picking an invoice fills the form below.
    if (onApply) {
        return (
            <Card className="mb-4 border-dashed p-5">
                <h2 className="mb-1 text-sm font-bold text-foreground">Prefill from a sales invoice or estimate</h2>
                <p className="mb-3 text-xs text-muted-foreground">Pick a CRM sales document to copy its line items, currency and reference into the form below — then choose a supplier and adjust costs before saving.</p>
                <Select className="w-full sm:max-w-md" value={invoiceId} placeholder="Search a sales invoice/estimate…" searchable
                    onChange={v => { setInvoiceId(v); const inv = invoices.find(i => String(i.id) === v); if (inv) onApply(inv); }}
                    options={invoiceOpts} />
            </Card>
        );
    }

    const copy = () => {
        if (!ready) return;
        setProcessing(true);
        router.post(
            `/procurement/${target}/from-invoice/${invoiceId}`,
            needsSupplier ? { procurement_supplier_id: supplierId } : {},
            { onFinish: () => setProcessing(false) },
        );
    };

    return (
        <Card className="mb-4 border-dashed p-5">
            <h2 className="mb-1 text-sm font-bold text-foreground">Copy from a sales invoice or estimate</h2>
            <p className="mb-3 text-xs text-muted-foreground">Start this document by copying the line items from an existing CRM sales document.</p>
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <Select className="w-full" value={invoiceId} placeholder="Select a sales invoice/estimate…" searchable onChange={setInvoiceId} options={invoiceOpts} />
                {needsSupplier && (
                    <Select className="w-full" value={supplierId} placeholder="Choose vendor for the PO…" searchable onChange={setSupplierId}
                        options={(suppliers ?? []).map(s => ({ value: String(s.id), label: s.name }))} />
                )}
            </div>
            <div className="mt-3">
                <Button type="button" variant="secondary" icon={Copy} disabled={!ready || processing} onClick={copy}>
                    {processing ? 'Copying…' : 'Copy to draft'}
                </Button>
            </div>
        </Card>
    );
}
