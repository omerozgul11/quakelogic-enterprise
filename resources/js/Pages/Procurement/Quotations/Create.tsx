import { Head, Link, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import { ProcurementLayout } from '@/Components/layout/ProcurementLayout';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { Select } from '@/Components/ui/Select';
import { LineItemsEditor, computeTotals, emptyLine, Line, ProductOpt } from '@/Components/procurement/LineItemsEditor';
import { formatCurrency } from '@/Lib/utils';
import { ArrowLeft, FileText } from 'lucide-react';

interface Props {
    suppliers: { id: number; name: string; currency: string }[];
    purchaseRequests: { id: number; number: string; title: string }[];
    products: ProductOpt[];
}

export default function QuotationCreate({ suppliers, purchaseRequests, products }: Props) {
    const form = useForm({
        procurement_supplier_id: '',
        procurement_purchase_request_id: '',
        reference_no: '',
        quote_date: new Date().toISOString().slice(0, 10),
        expiry_date: '',
        currency: 'USD',
        discount_total: '0',
        vendor_note: '',
        admin_note: '',
        terms: '',
        items: [{ ...emptyLine }] as Line[],
    });

    const { subtotal, tax } = computeTotals(form.data.items);
    const total = subtotal + tax - (parseFloat(form.data.discount_total) || 0);

    const submit = (e: FormEvent) => { e.preventDefault(); form.post('/procurement/quotations', { preserveScroll: true }); };

    return (
        <ProcurementLayout>
            <Head title="New Quotation · Procurement" />
            <form onSubmit={submit} className="mx-auto max-w-5xl px-4 py-6 sm:px-6">
                <Link href="/procurement/quotations" className="mb-4 inline-flex items-center gap-1.5 text-sm font-medium text-muted-foreground hover:text-foreground">
                    <ArrowLeft className="h-4 w-4" /> Quotations
                </Link>
                <div className="mb-6 flex items-center gap-3">
                    <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-brand-gradient text-white"><FileText className="h-5 w-5" /></div>
                    <h1 className="text-2xl font-bold tracking-tight text-foreground">New Quotation</h1>
                </div>

                <Card className="mb-4 p-5">
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label className="label">Vendor *</label>
                            <Select className="w-full" value={form.data.procurement_supplier_id} placeholder="Select vendor…" searchable
                                onChange={v => { const s = suppliers.find(su => String(su.id) === v); form.setData(d => ({ ...d, procurement_supplier_id: v, currency: s?.currency ?? d.currency })); }}
                                options={suppliers.map(s => ({ value: String(s.id), label: s.name }))} />
                            {form.errors.procurement_supplier_id && <p className="mt-1 text-xs text-destructive">{form.errors.procurement_supplier_id}</p>}
                        </div>
                        <div>
                            <label className="label">From purchase request</label>
                            <Select className="w-full" value={form.data.procurement_purchase_request_id} placeholder="— None —" searchable
                                onChange={v => form.setData('procurement_purchase_request_id', v)}
                                options={purchaseRequests.map(p => ({ value: String(p.id), label: `${p.number} · ${p.title}` }))} />
                        </div>
                        <div className="grid grid-cols-2 gap-3">
                            <div><label className="label">Quote date</label><input type="date" className="input" value={form.data.quote_date} onChange={e => form.setData('quote_date', e.target.value)} /></div>
                            <div><label className="label">Expiry</label><input type="date" className="input" value={form.data.expiry_date} onChange={e => form.setData('expiry_date', e.target.value)} /></div>
                        </div>
                        <div className="grid grid-cols-2 gap-3">
                            <div><label className="label">Reference #</label><input className="input" value={form.data.reference_no} onChange={e => form.setData('reference_no', e.target.value)} /></div>
                            <div><label className="label">Currency</label><input className="input uppercase" maxLength={3} value={form.data.currency} onChange={e => form.setData('currency', e.target.value.toUpperCase())} /></div>
                        </div>
                    </div>
                </Card>

                <Card className="mb-4 overflow-hidden">
                    <div className="border-b border-border px-5 py-3"><h2 className="text-sm font-bold uppercase tracking-wider text-muted-foreground/70">Quoted items</h2></div>
                    <LineItemsEditor items={form.data.items} onChange={v => form.setData('items', v)} products={products} currency={form.data.currency} errors={form.errors as Record<string, string>} />
                </Card>

                <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
                    <Card className="p-5 lg:col-span-2 space-y-3">
                        <div><label className="label">Vendor note</label><textarea className="input min-h-[60px]" value={form.data.vendor_note} onChange={e => form.setData('vendor_note', e.target.value)} /></div>
                        <div><label className="label">Terms</label><textarea className="input min-h-[60px]" value={form.data.terms} onChange={e => form.setData('terms', e.target.value)} /></div>
                    </Card>
                    <Card className="p-5">
                        <div className="space-y-2 text-sm">
                            <div className="flex items-center justify-between"><span className="text-muted-foreground">Subtotal</span><span className="text-foreground">{formatCurrency(subtotal, form.data.currency)}</span></div>
                            <div className="flex items-center justify-between"><span className="text-muted-foreground">Tax</span><span className="text-foreground">{formatCurrency(tax, form.data.currency)}</span></div>
                            <div className="flex items-center justify-between gap-2"><span className="text-muted-foreground">Discount</span><input type="number" step="0.01" min="0" className="input h-8 w-28 text-right" value={form.data.discount_total} onChange={e => form.setData('discount_total', e.target.value)} /></div>
                            <div className="flex items-center justify-between border-t border-border pt-2 text-base font-bold text-foreground"><span>Total</span><span>{formatCurrency(total, form.data.currency)}</span></div>
                        </div>
                        <Button type="submit" className="mt-4 w-full" disabled={form.processing}>{form.processing ? 'Creating…' : 'Create Quotation'}</Button>
                    </Card>
                </div>
            </form>
        </ProcurementLayout>
    );
}
