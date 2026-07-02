import { Head, Link, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import { ProcurementLayout } from '@/Components/layout/ProcurementLayout';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { Select } from '@/Components/ui/Select';
import { LineItemsEditor, computeTotals, emptyLine, Line, ProductOpt } from '@/Components/procurement/LineItemsEditor';
import { formatCurrency } from '@/Lib/utils';
import { ArrowLeft, Receipt } from 'lucide-react';

interface Props {
    suppliers: { id: number; name: string; currency: string }[];
    purchaseOrders: { id: number; number: string; supplier: string | null; supplier_id: number }[];
    products: ProductOpt[];
}

const frequencies = [
    { value: 'weekly', label: 'Weekly' }, { value: 'monthly', label: 'Monthly' },
    { value: 'quarterly', label: 'Quarterly' }, { value: 'yearly', label: 'Yearly' },
];

export default function BillCreate({ suppliers, purchaseOrders, products }: Props) {
    const form = useForm({
        procurement_supplier_id: '',
        procurement_purchase_order_id: '',
        vendor_invoice_number: '',
        bill_date: new Date().toISOString().slice(0, 10),
        due_date: '',
        currency: 'USD',
        shipping_amount: '0',
        discount_total: '0',
        recurring: false,
        recurring_frequency: 'monthly',
        recurring_total_cycles: '0',
        next_recurring_date: '',
        notes: '',
        terms: '',
        items: [{ ...emptyLine }] as Line[],
    });

    const { subtotal, tax } = computeTotals(form.data.items);
    const total = subtotal + tax + (parseFloat(form.data.shipping_amount) || 0) - (parseFloat(form.data.discount_total) || 0);

    const pickOrder = (v: string) => {
        const po = purchaseOrders.find(p => String(p.id) === v);
        form.setData(d => ({ ...d, procurement_purchase_order_id: v, procurement_supplier_id: po ? String(po.supplier_id) : d.procurement_supplier_id }));
    };

    const submit = (e: FormEvent) => { e.preventDefault(); form.post('/procurement/bills', { preserveScroll: true }); };

    return (
        <ProcurementLayout>
            <Head title="New Bill · Procurement" />
            <form onSubmit={submit} className="mx-auto max-w-5xl px-4 py-6 sm:px-6">
                <Link href="/procurement/bills" className="mb-4 inline-flex items-center gap-1.5 text-sm font-medium text-muted-foreground hover:text-foreground">
                    <ArrowLeft className="h-4 w-4" /> Bills
                </Link>
                <div className="mb-6 flex items-center gap-3">
                    <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-brand-gradient text-white"><Receipt className="h-5 w-5" /></div>
                    <h1 className="text-2xl font-bold tracking-tight text-foreground">New Bill</h1>
                </div>

                <Card className="mb-4 p-5">
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label className="label">From purchase order</label>
                            <Select className="w-full" value={form.data.procurement_purchase_order_id} placeholder="— None —" searchable
                                onChange={pickOrder} options={purchaseOrders.map(p => ({ value: String(p.id), label: `${p.number}${p.supplier ? ' · ' + p.supplier : ''}` }))} />
                        </div>
                        <div>
                            <label className="label">Vendor *</label>
                            <Select className="w-full" value={form.data.procurement_supplier_id} placeholder="Select vendor…" searchable
                                onChange={v => { const s = suppliers.find(su => String(su.id) === v); form.setData(d => ({ ...d, procurement_supplier_id: v, currency: s?.currency ?? d.currency })); }}
                                options={suppliers.map(s => ({ value: String(s.id), label: s.name }))} />
                            {form.errors.procurement_supplier_id && <p className="mt-1 text-xs text-destructive">{form.errors.procurement_supplier_id}</p>}
                        </div>
                        <div className="grid grid-cols-2 gap-3">
                            <div><label className="label">Bill date</label><input type="date" className="input" value={form.data.bill_date} onChange={e => form.setData('bill_date', e.target.value)} /></div>
                            <div><label className="label">Due date</label><input type="date" className="input" value={form.data.due_date} onChange={e => form.setData('due_date', e.target.value)} /></div>
                        </div>
                        <div className="grid grid-cols-2 gap-3">
                            <div><label className="label">Vendor invoice #</label><input className="input" value={form.data.vendor_invoice_number} onChange={e => form.setData('vendor_invoice_number', e.target.value)} /></div>
                            <div><label className="label">Currency</label><input className="input uppercase" maxLength={3} value={form.data.currency} onChange={e => form.setData('currency', e.target.value.toUpperCase())} /></div>
                        </div>
                    </div>
                </Card>

                <Card className="mb-4 overflow-hidden">
                    <div className="border-b border-border px-5 py-3"><h2 className="text-sm font-bold uppercase tracking-wider text-muted-foreground/70">Line items</h2></div>
                    <LineItemsEditor items={form.data.items} onChange={v => form.setData('items', v)} products={products} currency={form.data.currency} errors={form.errors as Record<string, string>} />
                </Card>

                <Card className="mb-4 p-5">
                    <label className="flex items-center gap-2 text-sm font-medium text-foreground">
                        <input type="checkbox" className="h-4 w-4 rounded border-border" checked={form.data.recurring} onChange={e => form.setData('recurring', e.target.checked)} />
                        Recurring bill
                    </label>
                    {form.data.recurring && (
                        <div className="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-3">
                            <div><label className="label">Frequency</label><Select className="w-full" value={form.data.recurring_frequency} onChange={v => form.setData('recurring_frequency', v)} options={frequencies} /></div>
                            <div><label className="label">Next date</label><input type="date" className="input" value={form.data.next_recurring_date} onChange={e => form.setData('next_recurring_date', e.target.value)} />{form.errors.next_recurring_date && <p className="mt-1 text-xs text-destructive">{form.errors.next_recurring_date}</p>}</div>
                            <div><label className="label"># cycles (0 = no limit)</label><input type="number" min="0" className="input" value={form.data.recurring_total_cycles} onChange={e => form.setData('recurring_total_cycles', e.target.value)} /></div>
                        </div>
                    )}
                </Card>

                <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
                    <Card className="p-5 lg:col-span-2 space-y-3">
                        <div><label className="label">Notes</label><textarea className="input min-h-[60px]" value={form.data.notes} onChange={e => form.setData('notes', e.target.value)} /></div>
                        <div><label className="label">Terms</label><textarea className="input min-h-[60px]" value={form.data.terms} onChange={e => form.setData('terms', e.target.value)} /></div>
                    </Card>
                    <Card className="p-5">
                        <div className="space-y-2 text-sm">
                            <div className="flex items-center justify-between"><span className="text-muted-foreground">Subtotal</span><span className="text-foreground">{formatCurrency(subtotal, form.data.currency)}</span></div>
                            <div className="flex items-center justify-between"><span className="text-muted-foreground">Tax</span><span className="text-foreground">{formatCurrency(tax, form.data.currency)}</span></div>
                            <div className="flex items-center justify-between gap-2"><span className="text-muted-foreground">Shipping</span><input type="number" step="0.01" min="0" className="input h-8 w-28 text-right" value={form.data.shipping_amount} onChange={e => form.setData('shipping_amount', e.target.value)} /></div>
                            <div className="flex items-center justify-between gap-2"><span className="text-muted-foreground">Discount</span><input type="number" step="0.01" min="0" className="input h-8 w-28 text-right" value={form.data.discount_total} onChange={e => form.setData('discount_total', e.target.value)} /></div>
                            <div className="flex items-center justify-between border-t border-border pt-2 text-base font-bold text-foreground"><span>Total</span><span>{formatCurrency(total, form.data.currency)}</span></div>
                        </div>
                        <Button type="submit" className="mt-4 w-full" disabled={form.processing}>{form.processing ? 'Creating…' : 'Create Bill'}</Button>
                    </Card>
                </div>
            </form>
        </ProcurementLayout>
    );
}
