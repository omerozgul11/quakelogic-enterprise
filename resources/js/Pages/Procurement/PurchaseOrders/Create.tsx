import { Head, Link, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import { ProcurementLayout } from '@/Components/layout/ProcurementLayout';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { Select } from '@/Components/ui/Select';
import { CopyFromInvoice, SourceInvoice } from '@/Components/procurement/CopyFromInvoice';
import { formatCurrency } from '@/Lib/utils';
import { ArrowLeft, Plus, Trash2, ShoppingCart } from 'lucide-react';

interface ProductOpt { id: number; sku: string; name: string; unit_cost: number }
interface Props {
    suppliers: { id: number; name: string; code: string; currency: string }[];
    warehouses: { id: number; name: string; code: string }[];
    products: ProductOpt[];
    companies: { id: number; name: string }[];
    sourceInvoices: SourceInvoice[];
}

interface Line { inventory_product_id: string; description: string; sku: string; quantity_ordered: string; unit_cost: string }

const emptyLine: Line = { inventory_product_id: '', description: '', sku: '', quantity_ordered: '1', unit_cost: '0' };

const emptyNewSupplier = {
    name: '', code: '', category: '', email: '', phone: '', website: '',
    payment_terms: '', tax_id: '', address_line1: '', city: '', state: '',
    postal_code: '', country: '', notes: '',
};

export default function PurchaseOrderCreate({ suppliers, warehouses, products, companies, sourceInvoices }: Props) {
    const [newSupplier, setNewSupplier] = useState(false);
    const [taxMode, setTaxMode] = useState<'percent' | 'amount'>('percent');
    const form = useForm({
        procurement_supplier_id: '',
        company_id: '',
        inventory_warehouse_id: '',
        order_date: new Date().toISOString().slice(0, 10),
        expected_date: '',
        currency: 'USD',
        tax_rate: '0',
        tax_amount: '0',
        shipping_amount: '0',
        notes: '',
        payment_terms: '',
        shipping_terms: '',
        use_ql_shipping_account: false,
        new_supplier: { ...emptyNewSupplier },
        items: [{ ...emptyLine }] as Line[],
    });

    const setNs = (patch: Partial<typeof emptyNewSupplier>) =>
        form.setData('new_supplier', { ...form.data.new_supplier, ...patch });
    const nsErr = (k: string) => (form.errors as Record<string, string>)[`new_supplier.${k}`];

    const setLine = (i: number, patch: Partial<Line>) => {
        const items = form.data.items.map((l, idx) => (idx === i ? { ...l, ...patch } : l));
        form.setData('items', items);
    };
    const pickProduct = (i: number, productId: string) => {
        const p = products.find(pr => String(pr.id) === productId);
        setLine(i, p
            ? { inventory_product_id: productId, description: p.name, sku: p.sku, unit_cost: String(p.unit_cost) }
            : { inventory_product_id: '' });
    };
    const addLine = () => form.setData('items', [...form.data.items, { ...emptyLine }]);
    const removeLine = (i: number) => form.setData('items', form.data.items.length > 1 ? form.data.items.filter((_, idx) => idx !== i) : form.data.items);

    const subtotal = form.data.items.reduce((s, l) => s + (parseFloat(l.quantity_ordered) || 0) * (parseFloat(l.unit_cost) || 0), 0);
    const taxAmount = taxMode === 'percent'
        ? subtotal * (parseFloat(form.data.tax_rate) || 0) / 100
        : (parseFloat(form.data.tax_amount) || 0);
    const total = subtotal + taxAmount + (parseFloat(form.data.shipping_amount) || 0);

    const productOptions = [{ value: '', label: '— Custom line —' }, ...products.map(p => ({ value: String(p.id), label: `${p.sku} · ${p.name}` }))];

    // Prefill the form from a picked CRM sales invoice/estimate: copy its line
    // items (sell price seeds unit cost — adjust before saving), currency and a
    // reference note. Supplier stays blank — an invoice's customer is not the PO's vendor.
    const applyInvoice = (inv: SourceInvoice) => {
        const items: Line[] = inv.items && inv.items.length
            ? inv.items.map(it => ({ inventory_product_id: '', description: it.description, sku: '', quantity_ordered: String(it.quantity), unit_cost: String(it.unit_cost) }))
            : [{ ...emptyLine }];
        setTaxMode('percent');
        form.setData(d => ({
            ...d,
            currency: inv.currency || d.currency,
            notes: d.notes ? d.notes : `From ${inv.kind} ${inv.number}`,
            items,
        }));
    };

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.transform(data => ({
            ...data,
            procurement_supplier_id: newSupplier ? null : data.procurement_supplier_id,
            new_supplier: newSupplier ? data.new_supplier : null,
            company_id: data.company_id || null,
        }));
        form.post('/procurement/purchase-orders', { preserveScroll: true });
    };
    const lineErr = (i: number, field: string) => (form.errors as Record<string, string>)[`items.${i}.${field}`];

    return (
        <ProcurementLayout>
            <Head title="New Purchase Order · Procurement" />
            <form onSubmit={submit} className="mx-auto max-w-5xl px-4 py-6 sm:px-6">
                <Link href="/procurement/purchase-orders" className="mb-4 inline-flex items-center gap-1.5 text-sm font-medium text-muted-foreground hover:text-foreground">
                    <ArrowLeft className="h-4 w-4" /> Purchase Orders
                </Link>

                <div className="mb-6 flex items-center gap-3">
                    <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-brand-gradient text-white"><ShoppingCart className="h-5 w-5" /></div>
                    <h1 className="text-2xl font-bold tracking-tight text-foreground">New Purchase Order</h1>
                </div>

                <CopyFromInvoice invoices={sourceInvoices} target="purchase-orders" onApply={applyInvoice} />

                <Card className="mb-4 p-5">
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <div className="flex items-center justify-between gap-2">
                                <label className="label">Supplier *</label>
                                <button type="button" onClick={() => setNewSupplier(v => !v)}
                                    className="text-xs font-medium text-primary hover:underline">
                                    {newSupplier ? 'Choose existing' : '+ New supplier'}
                                </button>
                            </div>
                            {newSupplier ? (
                                <>
                                    <input className="input" autoFocus placeholder="New supplier name"
                                        value={form.data.new_supplier.name} onChange={e => setNs({ name: e.target.value })} />
                                    {nsErr('name') && <p className="mt-1 text-xs text-destructive">{nsErr('name')}</p>}
                                </>
                            ) : (
                                <>
                                    <Select className="w-full" value={form.data.procurement_supplier_id} placeholder="Select supplier…"
                                        searchable searchPlaceholder="Search suppliers…"
                                        onChange={v => { const s = suppliers.find(su => String(su.id) === v); form.setData(d => ({ ...d, procurement_supplier_id: v, currency: s?.currency ?? d.currency })); }}
                                        options={suppliers.map(s => ({ value: String(s.id), label: `${s.name} (${s.code})` }))} />
                                    {form.errors.procurement_supplier_id && <p className="mt-1 text-xs text-destructive">{form.errors.procurement_supplier_id}</p>}
                                </>
                            )}
                        </div>
                        <div>
                            <label className="label">Receive into warehouse</label>
                            <Select className="w-full" value={form.data.inventory_warehouse_id} placeholder="— None (no stock impact) —"
                                onChange={v => form.setData('inventory_warehouse_id', v)}
                                options={warehouses.map(w => ({ value: String(w.id), label: w.code ? `${w.name} (${w.code})` : w.name }))} />
                        </div>
                        <div>
                            <label className="label">Client <span className="font-normal text-muted-foreground">(optional — the customer this is for)</span></label>
                            <Select className="w-full" value={form.data.company_id} placeholder="— None —" searchable searchPlaceholder="Search clients…"
                                onChange={v => form.setData('company_id', v)}
                                options={companies.map(c => ({ value: String(c.id), label: c.name }))} />
                        </div>
                        <div className="grid grid-cols-2 gap-3">
                            <div><label className="label">Order date</label><input type="date" className="input" value={form.data.order_date} onChange={e => form.setData('order_date', e.target.value)} /></div>
                            <div><label className="label">Expected</label><input type="date" className="input" value={form.data.expected_date} onChange={e => form.setData('expected_date', e.target.value)} /></div>
                        </div>
                        <div>
                            <label className="label">Currency</label>
                            <input className="input uppercase" maxLength={3} value={form.data.currency} onChange={e => form.setData('currency', e.target.value.toUpperCase())} />
                        </div>
                    </div>
                </Card>

                {newSupplier && (
                    <Card className="mb-4 p-5">
                        <h2 className="mb-3 text-sm font-bold uppercase tracking-wider text-muted-foreground/70">New supplier details</h2>
                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                            <div>
                                <label className="label">Code</label>
                                <input className="input" placeholder="Auto if blank" value={form.data.new_supplier.code} onChange={e => setNs({ code: e.target.value })} />
                                {nsErr('code') && <p className="mt-1 text-xs text-destructive">{nsErr('code')}</p>}
                            </div>
                            <div>
                                <label className="label">Category</label>
                                <input className="input" value={form.data.new_supplier.category} onChange={e => setNs({ category: e.target.value })} />
                            </div>
                            <div>
                                <label className="label">Payment terms</label>
                                <input className="input" placeholder="Net 30" value={form.data.new_supplier.payment_terms} onChange={e => setNs({ payment_terms: e.target.value })} />
                            </div>
                            <div>
                                <label className="label">Email</label>
                                <input type="email" className="input" value={form.data.new_supplier.email} onChange={e => setNs({ email: e.target.value })} />
                                {nsErr('email') && <p className="mt-1 text-xs text-destructive">{nsErr('email')}</p>}
                            </div>
                            <div>
                                <label className="label">Phone</label>
                                <input className="input" value={form.data.new_supplier.phone} onChange={e => setNs({ phone: e.target.value })} />
                            </div>
                            <div>
                                <label className="label">Website</label>
                                <input className="input" value={form.data.new_supplier.website} onChange={e => setNs({ website: e.target.value })} />
                            </div>
                            <div className="sm:col-span-2">
                                <label className="label">Address</label>
                                <input className="input" value={form.data.new_supplier.address_line1} onChange={e => setNs({ address_line1: e.target.value })} />
                            </div>
                            <div>
                                <label className="label">City</label>
                                <input className="input" value={form.data.new_supplier.city} onChange={e => setNs({ city: e.target.value })} />
                            </div>
                            <div>
                                <label className="label">State</label>
                                <input className="input" value={form.data.new_supplier.state} onChange={e => setNs({ state: e.target.value })} />
                            </div>
                            <div>
                                <label className="label">Postal code</label>
                                <input className="input" value={form.data.new_supplier.postal_code} onChange={e => setNs({ postal_code: e.target.value })} />
                            </div>
                            <div>
                                <label className="label">Country</label>
                                <input className="input" value={form.data.new_supplier.country} onChange={e => setNs({ country: e.target.value })} />
                            </div>
                            <div>
                                <label className="label">Tax ID</label>
                                <input className="input" value={form.data.new_supplier.tax_id} onChange={e => setNs({ tax_id: e.target.value })} />
                            </div>
                        </div>
                        <p className="mt-3 text-xs text-muted-foreground">Saved as a new supplier when you create the purchase order — currency {form.data.currency || 'USD'}.</p>
                    </Card>
                )}

                <Card className="mb-4 overflow-hidden">
                    <div className="border-b border-border px-5 py-3"><h2 className="text-sm font-bold uppercase tracking-wider text-muted-foreground/70">Line items</h2></div>
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="bg-secondary/40 text-left text-xs uppercase text-muted-foreground/70">
                                <tr>
                                    <th className="px-3 py-2">Product / description</th>
                                    <th className="px-3 py-2 w-24 text-right">Qty</th>
                                    <th className="px-3 py-2 w-32 text-right">Unit cost</th>
                                    <th className="px-3 py-2 w-32 text-right">Line total</th>
                                    <th className="px-2 py-2 w-10" />
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {form.data.items.map((l, i) => {
                                    const lineTotal = (parseFloat(l.quantity_ordered) || 0) * (parseFloat(l.unit_cost) || 0);
                                    return (
                                        <tr key={i} className="align-top">
                                            <td className="px-3 py-2">
                                                <Select className="w-full" value={l.inventory_product_id} placeholder="— Custom line —"
                                                    searchable searchPlaceholder="Search products by name or SKU…"
                                                    onChange={v => pickProduct(i, v)} options={productOptions} />
                                                <input className="input mt-1.5 h-9" placeholder="Description *" value={l.description} onChange={e => setLine(i, { description: e.target.value })} />
                                                {lineErr(i, 'description') && <p className="mt-1 text-xs text-destructive">{lineErr(i, 'description')}</p>}
                                            </td>
                                            <td className="px-3 py-2 text-right">
                                                <input type="number" step="0.001" min="0" className="input h-9 text-right" value={l.quantity_ordered} onChange={e => setLine(i, { quantity_ordered: e.target.value })} />
                                                {lineErr(i, 'quantity_ordered') && <p className="mt-1 text-xs text-destructive">{lineErr(i, 'quantity_ordered')}</p>}
                                            </td>
                                            <td className="px-3 py-2 text-right">
                                                <input type="number" step="0.0001" min="0" className="input h-9 text-right" value={l.unit_cost} onChange={e => setLine(i, { unit_cost: e.target.value })} />
                                            </td>
                                            <td className="px-3 py-2 text-right font-medium text-foreground">{formatCurrency(lineTotal, form.data.currency)}</td>
                                            <td className="px-2 py-2 text-right">
                                                <button type="button" onClick={() => removeLine(i)} className="rounded-md p-1.5 text-muted-foreground hover:bg-destructive/10 hover:text-destructive"><Trash2 className="h-4 w-4" /></button>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                    <div className="px-3 py-3">
                        <Button type="button" variant="ghost" size="sm" icon={Plus} onClick={addLine}>Add line</Button>
                    </div>
                    {typeof form.errors.items === 'string' && <p className="px-5 pb-3 text-xs text-destructive">{form.errors.items}</p>}
                </Card>

                <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
                    <Card className="p-5 lg:col-span-2">
                        <h2 className="mb-3 text-sm font-bold uppercase tracking-wider text-muted-foreground/70">Terms & notes</h2>
                        <div className="mb-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div>
                                <label className="label">Payment terms</label>
                                <input className="input" list="po-payment-terms" placeholder="Net 30" value={form.data.payment_terms} onChange={e => form.setData('payment_terms', e.target.value)} />
                                <datalist id="po-payment-terms">
                                    <option value="Due on receipt" /><option value="Net 15" /><option value="Net 30" /><option value="Net 45" /><option value="Net 60" /><option value="50% deposit, balance on delivery" />
                                </datalist>
                            </div>
                            <div>
                                <label className="label">Shipping terms</label>
                                <input className="input" list="po-shipping-terms" placeholder="DHL" value={form.data.shipping_terms} onChange={e => form.setData('shipping_terms', e.target.value)} />
                                <datalist id="po-shipping-terms">
                                    <option value="DHL" /><option value="FedEx" /><option value="UPS" /><option value="Freight / LTL" /><option value="Supplier delivery" /><option value="Customer pickup" /><option value="EXW (Ex Works)" /><option value="FOB" /><option value="DDP (Delivered Duty Paid)" />
                                </datalist>
                            </div>
                        </div>
                        <label className="mb-3 flex items-center gap-2 text-sm text-foreground">
                            <input type="checkbox" className="h-4 w-4 rounded border-border" checked={form.data.use_ql_shipping_account} onChange={e => form.setData('use_ql_shipping_account', e.target.checked)} />
                            Use QuakeLogic account for shipping
                            <span className="text-xs text-muted-foreground">(added to the vendor email draft)</span>
                        </label>
                        <label className="label">Notes</label>
                        <textarea className="input min-h-[96px]" value={form.data.notes} onChange={e => form.setData('notes', e.target.value)} placeholder="Delivery instructions, references…" />
                    </Card>
                    <Card className="p-5">
                        <div className="space-y-2 text-sm">
                            <Row label="Subtotal" value={formatCurrency(subtotal, form.data.currency)} />
                            <div className="flex items-center justify-between gap-2">
                                <span className="flex items-center gap-1.5 text-muted-foreground">Tax
                                    <span className="inline-flex overflow-hidden rounded-md border border-border">
                                        <button type="button" onClick={() => { setTaxMode('percent'); form.setData('tax_amount', '0'); }}
                                            className={`px-2 py-0.5 text-xs ${taxMode === 'percent' ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:bg-secondary'}`}>%</button>
                                        <button type="button" onClick={() => { setTaxMode('amount'); form.setData('tax_rate', '0'); }}
                                            className={`px-2 py-0.5 text-xs ${taxMode === 'amount' ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:bg-secondary'}`}>flat</button>
                                    </span>
                                    {taxMode === 'percent'
                                        ? <><input type="number" step="0.01" min="0" max="100" className="input h-8 w-16 text-right" value={form.data.tax_rate} onChange={e => form.setData('tax_rate', e.target.value)} />%</>
                                        : <input type="number" step="0.01" min="0" className="input h-8 w-28 text-right" value={form.data.tax_amount} onChange={e => form.setData('tax_amount', e.target.value)} />}
                                </span>
                                <span className="text-foreground">{formatCurrency(taxAmount, form.data.currency)}</span>
                            </div>
                            <div className="flex items-center justify-between gap-2">
                                <span className="text-muted-foreground">Shipping</span>
                                <input type="number" step="0.01" min="0" className="input h-8 w-28 text-right" value={form.data.shipping_amount} onChange={e => form.setData('shipping_amount', e.target.value)} />
                            </div>
                            <div className="flex items-center justify-between border-t border-border pt-2 text-base font-bold text-foreground">
                                <span>Total</span><span>{formatCurrency(total, form.data.currency)}</span>
                            </div>
                        </div>
                        <Button type="submit" className="mt-4 w-full" disabled={form.processing}>
                            {form.processing ? 'Creating…' : 'Create Purchase Order'}
                        </Button>
                    </Card>
                </div>
            </form>
        </ProcurementLayout>
    );
}

function Row({ label, value }: { label: string; value: string }) {
    return <div className="flex items-center justify-between"><span className="text-muted-foreground">{label}</span><span className="text-foreground">{value}</span></div>;
}
