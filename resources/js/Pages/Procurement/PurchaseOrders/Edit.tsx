import { Head, Link, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import { ProcurementLayout } from '@/Components/layout/ProcurementLayout';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { Select } from '@/Components/ui/Select';
import { formatCurrency } from '@/Lib/utils';
import { ArrowLeft, Plus, Trash2, ShoppingCart } from 'lucide-react';

interface ProductOpt { id: number; sku: string; name: string; unit_cost: number }
interface Line { inventory_product_id: string; description: string; sku: string; quantity_ordered: string; unit_cost: string }
interface Order {
    id: number; number: string;
    procurement_supplier_id: string; company_id: string; inventory_warehouse_id: string;
    order_date: string | null; expected_date: string | null; currency: string;
    tax_rate: number; tax_amount: number; shipping_amount: number; notes: string | null;
    payment_terms: string | null; shipping_terms: string | null;
    items: Line[];
}
interface Props {
    order: Order;
    suppliers: { id: number; name: string; code: string; currency: string }[];
    warehouses: { id: number; name: string; code: string }[];
    products: ProductOpt[];
    companies: { id: number; name: string }[];
}

export default function PurchaseOrderEdit({ order, suppliers, warehouses, products, companies }: Props) {
    const [taxMode, setTaxMode] = useState<'percent' | 'amount'>(order.tax_rate > 0 ? 'percent' : (order.tax_amount > 0 ? 'amount' : 'percent'));
    const form = useForm({
        procurement_supplier_id: order.procurement_supplier_id,
        company_id: order.company_id,
        inventory_warehouse_id: order.inventory_warehouse_id,
        order_date: order.order_date ?? '',
        expected_date: order.expected_date ?? '',
        currency: order.currency,
        tax_rate: String(order.tax_rate),
        tax_amount: String(order.tax_amount),
        shipping_amount: String(order.shipping_amount),
        notes: order.notes ?? '',
        payment_terms: order.payment_terms ?? '',
        shipping_terms: order.shipping_terms ?? '',
        items: (order.items.length ? order.items : [{ inventory_product_id: '', description: '', sku: '', quantity_ordered: '1', unit_cost: '0' }]) as Line[],
    });

    const setLine = (i: number, patch: Partial<Line>) =>
        form.setData('items', form.data.items.map((l, idx) => (idx === i ? { ...l, ...patch } : l)));
    const pickProduct = (i: number, productId: string) => {
        const p = products.find(pr => String(pr.id) === productId);
        setLine(i, p ? { inventory_product_id: productId, description: p.name, sku: p.sku, unit_cost: String(p.unit_cost) } : { inventory_product_id: '' });
    };
    const addLine = () => form.setData('items', [...form.data.items, { inventory_product_id: '', description: '', sku: '', quantity_ordered: '1', unit_cost: '0' }]);
    const removeLine = (i: number) => form.setData('items', form.data.items.length > 1 ? form.data.items.filter((_, idx) => idx !== i) : form.data.items);

    const subtotal = form.data.items.reduce((s, l) => s + (parseFloat(l.quantity_ordered) || 0) * (parseFloat(l.unit_cost) || 0), 0);
    const taxAmount = taxMode === 'percent' ? subtotal * (parseFloat(form.data.tax_rate) || 0) / 100 : (parseFloat(form.data.tax_amount) || 0);
    const total = subtotal + taxAmount + (parseFloat(form.data.shipping_amount) || 0);
    const productOptions = [{ value: '', label: '— Custom line —' }, ...products.map(p => ({ value: String(p.id), label: `${p.sku} · ${p.name}` }))];
    const lineErr = (i: number, field: string) => (form.errors as Record<string, string>)[`items.${i}.${field}`];

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.put(`/procurement/purchase-orders/${order.id}`, { preserveScroll: true });
    };

    return (
        <ProcurementLayout>
            <Head title={`Edit ${order.number} · Procurement`} />
            <form onSubmit={submit} className="mx-auto max-w-5xl px-4 py-6 sm:px-6">
                <Link href={`/procurement/purchase-orders/${order.id}`} className="mb-4 inline-flex items-center gap-1.5 text-sm font-medium text-muted-foreground hover:text-foreground">
                    <ArrowLeft className="h-4 w-4" /> {order.number}
                </Link>

                <div className="mb-6 flex items-center gap-3">
                    <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-brand-gradient text-white"><ShoppingCart className="h-5 w-5" /></div>
                    <h1 className="text-2xl font-bold tracking-tight text-foreground">Edit {order.number}</h1>
                </div>

                <Card className="mb-4 p-5">
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label className="label">Supplier *</label>
                            <Select className="w-full" value={form.data.procurement_supplier_id} placeholder="Select supplier…"
                                searchable searchPlaceholder="Search suppliers…"
                                onChange={v => { const s = suppliers.find(su => String(su.id) === v); form.setData(d => ({ ...d, procurement_supplier_id: v, currency: s?.currency ?? d.currency })); }}
                                options={suppliers.map(s => ({ value: String(s.id), label: `${s.name} (${s.code})` }))} />
                            {form.errors.procurement_supplier_id && <p className="mt-1 text-xs text-destructive">{form.errors.procurement_supplier_id}</p>}
                        </div>
                        <div>
                            <label className="label">Receive into warehouse</label>
                            <Select className="w-full" value={form.data.inventory_warehouse_id} placeholder="— None (no stock impact) —"
                                onChange={v => form.setData('inventory_warehouse_id', v)}
                                options={warehouses.map(w => ({ value: String(w.id), label: w.code ? `${w.name} (${w.code})` : w.name }))} />
                        </div>
                        <div>
                            <label className="label">Client <span className="font-normal text-muted-foreground">(optional)</span></label>
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
                        <label className="label">Notes</label>
                        <textarea className="input min-h-[96px]" value={form.data.notes} onChange={e => form.setData('notes', e.target.value)} placeholder="Delivery instructions, references…" />
                    </Card>
                    <Card className="p-5">
                        <div className="space-y-2 text-sm">
                            <div className="flex items-center justify-between"><span className="text-muted-foreground">Subtotal</span><span className="text-foreground">{formatCurrency(subtotal, form.data.currency)}</span></div>
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
                            {form.processing ? 'Saving…' : 'Save changes'}
                        </Button>
                    </Card>
                </div>
            </form>
        </ProcurementLayout>
    );
}
