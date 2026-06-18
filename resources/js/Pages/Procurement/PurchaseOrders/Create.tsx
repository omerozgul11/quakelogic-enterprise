import { Head, Link, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import { ProcurementLayout } from '@/Components/layout/ProcurementLayout';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { Select } from '@/Components/ui/Select';
import { formatCurrency } from '@/Lib/utils';
import { ArrowLeft, Plus, Trash2, ShoppingCart } from 'lucide-react';

interface ProductOpt { id: number; sku: string; name: string; unit_cost: number }
interface Props {
    suppliers: { id: number; name: string; code: string; currency: string }[];
    warehouses: { id: number; name: string; code: string }[];
    products: ProductOpt[];
}

interface Line { inventory_product_id: string; description: string; sku: string; quantity_ordered: string; unit_cost: string }

const emptyLine: Line = { inventory_product_id: '', description: '', sku: '', quantity_ordered: '1', unit_cost: '0' };

export default function PurchaseOrderCreate({ suppliers, warehouses, products }: Props) {
    const form = useForm({
        procurement_supplier_id: '',
        inventory_warehouse_id: '',
        order_date: new Date().toISOString().slice(0, 10),
        expected_date: '',
        currency: 'USD',
        tax_rate: '0',
        shipping_amount: '0',
        notes: '',
        items: [{ ...emptyLine }] as Line[],
    });

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
    const taxAmount = subtotal * (parseFloat(form.data.tax_rate) || 0) / 100;
    const total = subtotal + taxAmount + (parseFloat(form.data.shipping_amount) || 0);

    const productOptions = [{ value: '', label: '— Custom line —' }, ...products.map(p => ({ value: String(p.id), label: `${p.sku} · ${p.name}` }))];

    const submit = (e: FormEvent) => {
        e.preventDefault();
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

                <Card className="mb-4 p-5">
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label className="label">Supplier *</label>
                            <Select className="w-full" value={form.data.procurement_supplier_id} placeholder="Select supplier…"
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
                        <label className="label">Notes</label>
                        <textarea className="input min-h-[96px]" value={form.data.notes} onChange={e => form.setData('notes', e.target.value)} placeholder="Delivery instructions, references…" />
                    </Card>
                    <Card className="p-5">
                        <div className="space-y-2 text-sm">
                            <Row label="Subtotal" value={formatCurrency(subtotal, form.data.currency)} />
                            <div className="flex items-center justify-between gap-2">
                                <span className="flex items-center gap-1 text-muted-foreground">Tax
                                    <input type="number" step="0.01" min="0" max="100" className="input h-8 w-16 text-right" value={form.data.tax_rate} onChange={e => form.setData('tax_rate', e.target.value)} />%
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
