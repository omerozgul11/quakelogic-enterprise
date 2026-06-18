import { Head, Link, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import { ManufacturingLayout } from '@/Components/layout/ManufacturingLayout';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { Select } from '@/Components/ui/Select';
import { ArrowLeft, Factory } from 'lucide-react';

interface Props {
    products: { id: number; sku: string; name: string }[];
    warehouses: { id: number; name: string; code: string }[];
    boms: { id: number; name: string; version: string; inventory_product_id: number }[];
}

export default function WorkOrderCreate({ products, warehouses, boms }: Props) {
    const params = new URLSearchParams(typeof window !== 'undefined' ? window.location.search : '');
    const preBom = boms.find(b => String(b.id) === params.get('bom'));

    const form = useForm({
        inventory_product_id: preBom ? String(preBom.inventory_product_id) : '',
        manufacturing_bom_id: preBom ? String(preBom.id) : '',
        inventory_warehouse_id: '',
        quantity_planned: '1',
        scheduled_date: new Date().toISOString().slice(0, 10),
        notes: '',
    });

    const bomsForProduct = form.data.inventory_product_id
        ? boms.filter(b => String(b.inventory_product_id) === form.data.inventory_product_id)
        : [];

    const pickProduct = (v: string) => {
        const firstBom = boms.find(b => String(b.inventory_product_id) === v);
        form.setData(d => ({ ...d, inventory_product_id: v, manufacturing_bom_id: firstBom ? String(firstBom.id) : '' }));
    };

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post('/manufacturing/work-orders', { preserveScroll: true });
    };

    return (
        <ManufacturingLayout>
            <Head title="New Work Order · Manufacturing" />
            <form onSubmit={submit} className="mx-auto max-w-2xl px-4 py-6 sm:px-6">
                <Link href="/manufacturing/work-orders" className="mb-4 inline-flex items-center gap-1.5 text-sm font-medium text-muted-foreground hover:text-foreground">
                    <ArrowLeft className="h-4 w-4" /> Work Orders
                </Link>

                <div className="mb-6 flex items-center gap-3">
                    <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-brand-gradient text-white"><Factory className="h-5 w-5" /></div>
                    <h1 className="text-2xl font-bold tracking-tight text-foreground">New Work Order</h1>
                </div>

                <Card className="p-5">
                    <div className="space-y-4">
                        <div>
                            <label className="label">Product to build *</label>
                            <Select className="w-full" value={form.data.inventory_product_id} placeholder="Select product…"
                                onChange={pickProduct} options={products.map(p => ({ value: String(p.id), label: `${p.sku} · ${p.name}` }))} />
                            {form.errors.inventory_product_id && <p className="mt-1 text-xs text-destructive">{form.errors.inventory_product_id}</p>}
                        </div>
                        <div>
                            <label className="label">Bill of materials</label>
                            <Select className="w-full" value={form.data.manufacturing_bom_id} placeholder={bomsForProduct.length ? 'Select BOM…' : 'No active BOM for this product'}
                                onChange={v => form.setData('manufacturing_bom_id', v)}
                                options={bomsForProduct.map(b => ({ value: String(b.id), label: `${b.name} (${b.version})` }))} />
                            {bomsForProduct.length === 0 && form.data.inventory_product_id && (
                                <p className="mt-1 text-xs text-amber-600">No active BOM — you can still create the work order, but it can't be built until a BOM exists.</p>
                            )}
                        </div>
                        <div className="grid grid-cols-2 gap-3">
                            <div>
                                <label className="label">Warehouse *</label>
                                <Select className="w-full" value={form.data.inventory_warehouse_id} placeholder="Select…"
                                    onChange={v => form.setData('inventory_warehouse_id', v)} options={warehouses.map(w => ({ value: String(w.id), label: w.code ? `${w.name} (${w.code})` : w.name }))} />
                                {form.errors.inventory_warehouse_id && <p className="mt-1 text-xs text-destructive">{form.errors.inventory_warehouse_id}</p>}
                            </div>
                            <div>
                                <label className="label">Quantity *</label>
                                <input type="number" step="0.001" min="0" className="input" value={form.data.quantity_planned} onChange={e => form.setData('quantity_planned', e.target.value)} />
                                {form.errors.quantity_planned && <p className="mt-1 text-xs text-destructive">{form.errors.quantity_planned}</p>}
                            </div>
                        </div>
                        <div>
                            <label className="label">Scheduled date</label>
                            <input type="date" className="input" value={form.data.scheduled_date} onChange={e => form.setData('scheduled_date', e.target.value)} />
                        </div>
                        <div>
                            <label className="label">Notes</label>
                            <textarea className="input min-h-[64px]" value={form.data.notes} onChange={e => form.setData('notes', e.target.value)} />
                        </div>
                        <div className="flex justify-end">
                            <Button type="submit" disabled={form.processing}>{form.processing ? 'Creating…' : 'Create Work Order'}</Button>
                        </div>
                    </div>
                </Card>
            </form>
        </ManufacturingLayout>
    );
}
