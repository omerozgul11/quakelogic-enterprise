import { Head, Link, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import { ManufacturingLayout } from '@/Components/layout/ManufacturingLayout';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { Select } from '@/Components/ui/Select';
import { formatCurrency } from '@/Lib/utils';
import { ArrowLeft, Plus, Trash2, ListTree } from 'lucide-react';

interface ProductOpt { id: number; sku: string; name: string; unit_cost: number }
interface Line { inventory_product_id: string; quantity_per: string; notes: string }
interface BomData {
    id: number;
    inventory_product_id: number;
    name: string; version: string; status: string; output_quantity: number; is_default: boolean; notes: string | null;
    items: Line[];
}

interface Props {
    bom: BomData | null;
    products: ProductOpt[];
    statuses: { value: string; label: string }[];
}

const emptyLine: Line = { inventory_product_id: '', quantity_per: '1', notes: '' };

export default function BomEdit({ bom, products, statuses }: Props) {
    const isEdit = !!bom;
    const form = useForm({
        inventory_product_id: bom ? String(bom.inventory_product_id) : '',
        name: bom?.name ?? '',
        version: bom?.version ?? 'v1',
        status: bom?.status ?? 'active',
        output_quantity: bom ? String(bom.output_quantity) : '1',
        is_default: bom?.is_default ?? false,
        notes: bom?.notes ?? '',
        items: (bom?.items?.length ? bom.items : [{ ...emptyLine }]) as Line[],
    });

    const productOptions = products.map(p => ({ value: String(p.id), label: `${p.sku} · ${p.name}` }));
    const setLine = (i: number, patch: Partial<Line>) => form.setData('items', form.data.items.map((l, idx) => idx === i ? { ...l, ...patch } : l));
    const addLine = () => form.setData('items', [...form.data.items, { ...emptyLine }]);
    const removeLine = (i: number) => form.setData('items', form.data.items.length > 1 ? form.data.items.filter((_, idx) => idx !== i) : form.data.items);

    const costOf = (id: string) => products.find(p => String(p.id) === id)?.unit_cost ?? 0;
    const outQty = parseFloat(form.data.output_quantity) || 1;
    const batchCost = form.data.items.reduce((s, l) => s + (parseFloat(l.quantity_per) || 0) * costOf(l.inventory_product_id), 0);
    const unitCost = batchCost / outQty;

    const submit = (e: FormEvent) => {
        e.preventDefault();
        if (isEdit) form.put(`/manufacturing/boms/${bom!.id}`, { preserveScroll: true });
        else form.post('/manufacturing/boms', { preserveScroll: true });
    };
    const lineErr = (i: number, f: string) => (form.errors as Record<string, string>)[`items.${i}.${f}`];

    return (
        <ManufacturingLayout>
            <Head title={`${isEdit ? 'Edit' : 'New'} BOM · Manufacturing`} />
            <form onSubmit={submit} className="mx-auto max-w-4xl px-4 py-6 sm:px-6">
                <Link href="/manufacturing/boms" className="mb-4 inline-flex items-center gap-1.5 text-sm font-medium text-muted-foreground hover:text-foreground">
                    <ArrowLeft className="h-4 w-4" /> BOMs
                </Link>

                <div className="mb-6 flex items-center gap-3">
                    <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-brand-gradient text-white"><ListTree className="h-5 w-5" /></div>
                    <h1 className="text-2xl font-bold tracking-tight text-foreground">{isEdit ? 'Edit BOM' : 'New BOM'}</h1>
                </div>

                <Card className="mb-4 p-5">
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div className="sm:col-span-2">
                            <label className="label">Output product *</label>
                            <Select className="w-full" value={form.data.inventory_product_id} placeholder="Select the product this BOM builds…"
                                onChange={v => form.setData('inventory_product_id', v)} options={productOptions} />
                            {form.errors.inventory_product_id && <p className="mt-1 text-xs text-destructive">{form.errors.inventory_product_id}</p>}
                        </div>
                        <div>
                            <label className="label">BOM name *</label>
                            <input className="input" value={form.data.name} onChange={e => form.setData('name', e.target.value)} placeholder="e.g. QUAKELY-PRO Assembly" />
                            {form.errors.name && <p className="mt-1 text-xs text-destructive">{form.errors.name}</p>}
                        </div>
                        <div className="grid grid-cols-3 gap-3">
                            <div><label className="label">Version</label><input className="input" value={form.data.version} onChange={e => form.setData('version', e.target.value)} /></div>
                            <div>
                                <label className="label">Status</label>
                                <Select className="w-full" value={form.data.status} onChange={v => form.setData('status', v)} options={statuses} />
                            </div>
                            <div><label className="label">Yields</label><input type="number" step="0.001" min="0" className="input" value={form.data.output_quantity} onChange={e => form.setData('output_quantity', e.target.value)} /></div>
                        </div>
                    </div>
                </Card>

                <Card className="mb-4 overflow-hidden">
                    <div className="border-b border-border px-5 py-3"><h2 className="text-sm font-bold uppercase tracking-wider text-muted-foreground/70">Components (per {outQty} output)</h2></div>
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="bg-secondary/40 text-left text-xs uppercase text-muted-foreground/70">
                                <tr>
                                    <th className="px-3 py-2">Component</th>
                                    <th className="px-3 py-2 w-28 text-right">Qty / batch</th>
                                    <th className="px-3 py-2 w-32 text-right">Ext. cost</th>
                                    <th className="px-2 py-2 w-10" />
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {form.data.items.map((l, i) => (
                                    <tr key={i}>
                                        <td className="px-3 py-2">
                                            <Select className="w-full" value={l.inventory_product_id} placeholder="Select component…" onChange={v => setLine(i, { inventory_product_id: v })} options={productOptions} />
                                            {lineErr(i, 'inventory_product_id') && <p className="mt-1 text-xs text-destructive">{lineErr(i, 'inventory_product_id')}</p>}
                                        </td>
                                        <td className="px-3 py-2 text-right">
                                            <input type="number" step="0.001" min="0" className="input h-9 text-right" value={l.quantity_per} onChange={e => setLine(i, { quantity_per: e.target.value })} />
                                            {lineErr(i, 'quantity_per') && <p className="mt-1 text-xs text-destructive">{lineErr(i, 'quantity_per')}</p>}
                                        </td>
                                        <td className="px-3 py-2 text-right text-muted-foreground">{formatCurrency((parseFloat(l.quantity_per) || 0) * costOf(l.inventory_product_id))}</td>
                                        <td className="px-2 py-2 text-right">
                                            <button type="button" onClick={() => removeLine(i)} className="rounded-md p-1.5 text-muted-foreground hover:bg-destructive/10 hover:text-destructive"><Trash2 className="h-4 w-4" /></button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <div className="flex items-center justify-between px-3 py-3">
                        <Button type="button" variant="ghost" size="sm" icon={Plus} onClick={addLine}>Add component</Button>
                        <span className="text-sm text-muted-foreground">Est. unit cost <span className="font-semibold text-foreground">{formatCurrency(unitCost)}</span></span>
                    </div>
                    {typeof form.errors.items === 'string' && <p className="px-5 pb-3 text-xs text-destructive">{form.errors.items}</p>}
                </Card>

                <Card className="p-5">
                    <label className="label">Notes</label>
                    <textarea className="input min-h-[64px]" value={form.data.notes} onChange={e => form.setData('notes', e.target.value)} />
                    <div className="mt-4 flex items-center justify-between">
                        <label className="flex items-center gap-2 text-sm text-foreground">
                            <input type="checkbox" checked={form.data.is_default} onChange={e => form.setData('is_default', e.target.checked)} /> Default BOM for this product
                        </label>
                        <Button type="submit" disabled={form.processing}>{form.processing ? 'Saving…' : isEdit ? 'Save BOM' : 'Create BOM'}</Button>
                    </div>
                </Card>
            </form>
        </ManufacturingLayout>
    );
}
