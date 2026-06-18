import { useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import { Modal } from '@/Components/ui/Modal';
import { Button } from '@/Components/ui/Button';
import { Select } from '@/Components/ui/Select';

export interface EditableProduct {
    id: number;
    sku?: string;
    name?: string;
    type?: string;
    category?: string | null;
    description?: string | null;
    unit_of_measure?: string;
    barcode?: string | null;
    manufacturer?: string | null;
    mpn?: string | null;
    unit_cost?: number;
    unit_price?: number;
    currency?: string;
    reorder_point?: number | null;
    reorder_quantity?: number | null;
    lead_time_days?: number | null;
    weight?: number | null;
    is_serialized?: boolean;
    track_inventory?: boolean;
    is_active?: boolean;
}

interface Props {
    open: boolean;
    onClose: () => void;
    product?: EditableProduct | null;
    types: { value: string; label: string }[];
}

export function ProductFormModal({ open, onClose, product, types }: Props) {
    const isEdit = !!product;
    const form = useForm({
        sku: product?.sku ?? '',
        name: product?.name ?? '',
        type: product?.type ?? 'good',
        category: product?.category ?? '',
        description: product?.description ?? '',
        unit_of_measure: product?.unit_of_measure ?? 'each',
        barcode: product?.barcode ?? '',
        manufacturer: product?.manufacturer ?? '',
        mpn: product?.mpn ?? '',
        unit_cost: product?.unit_cost ?? 0,
        unit_price: product?.unit_price ?? 0,
        currency: product?.currency ?? 'USD',
        reorder_point: product?.reorder_point ?? '',
        reorder_quantity: product?.reorder_quantity ?? '',
        lead_time_days: product?.lead_time_days ?? '',
        weight: product?.weight ?? '',
        is_serialized: product?.is_serialized ?? false,
        track_inventory: product?.track_inventory ?? true,
        is_active: product?.is_active ?? true,
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        const opts = { preserveScroll: true, onSuccess: () => { if (!isEdit) form.reset(); onClose(); } };
        if (isEdit) form.put(`/inventory/products/${product!.id}`, opts);
        else form.post('/inventory/products', opts);
    };

    const err = (k: keyof typeof form.data) => form.errors[k as keyof typeof form.errors];

    return (
        <Modal
            open={open}
            onClose={onClose}
            size="lg"
            title={isEdit ? 'Edit Product' : 'Add Product'}
            description={isEdit ? 'Update this product’s master details.' : 'Add a product to the catalog.'}
            footer={
                <>
                    <Button variant="ghost" onClick={onClose}>Cancel</Button>
                    <Button onClick={submit as unknown as () => void} disabled={form.processing}>
                        {form.processing ? 'Saving…' : isEdit ? 'Save Changes' : 'Add Product'}
                    </Button>
                </>
            }
        >
            <form onSubmit={submit} className="space-y-4">
                <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <div>
                        <label className="label">SKU *</label>
                        <input className="input" value={form.data.sku} onChange={e => form.setData('sku', e.target.value)} autoFocus />
                        {err('sku') && <p className="mt-1 text-xs text-destructive">{err('sku')}</p>}
                    </div>
                    <div className="sm:col-span-2">
                        <label className="label">Name *</label>
                        <input className="input" value={form.data.name} onChange={e => form.setData('name', e.target.value)} />
                        {err('name') && <p className="mt-1 text-xs text-destructive">{err('name')}</p>}
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <div>
                        <label className="label">Type</label>
                        <Select className="w-full" value={form.data.type} onChange={v => form.setData('type', v)}
                            options={types} />
                    </div>
                    <div>
                        <label className="label">Category</label>
                        <input className="input" value={form.data.category} onChange={e => form.setData('category', e.target.value)} placeholder="Sensors…" />
                    </div>
                    <div>
                        <label className="label">Unit of measure</label>
                        <input className="input" value={form.data.unit_of_measure} onChange={e => form.setData('unit_of_measure', e.target.value)} placeholder="each" />
                    </div>
                </div>

                <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <div>
                        <label className="label">Unit cost</label>
                        <input type="number" step="0.0001" min="0" className="input" value={form.data.unit_cost} onChange={e => form.setData('unit_cost', e.target.value as unknown as number)} />
                        {err('unit_cost') && <p className="mt-1 text-xs text-destructive">{err('unit_cost')}</p>}
                    </div>
                    <div>
                        <label className="label">Sell price</label>
                        <input type="number" step="0.0001" min="0" className="input" value={form.data.unit_price} onChange={e => form.setData('unit_price', e.target.value as unknown as number)} />
                    </div>
                    <div>
                        <label className="label">Currency</label>
                        <input className="input uppercase" maxLength={3} value={form.data.currency} onChange={e => form.setData('currency', e.target.value.toUpperCase())} />
                    </div>
                    <div>
                        <label className="label">Lead time (days)</label>
                        <input type="number" min="0" className="input" value={form.data.lead_time_days} onChange={e => form.setData('lead_time_days', e.target.value as unknown as number)} />
                    </div>
                </div>

                <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <div>
                        <label className="label">Reorder point</label>
                        <input type="number" step="0.001" min="0" className="input" value={form.data.reorder_point} onChange={e => form.setData('reorder_point', e.target.value as unknown as number)} />
                    </div>
                    <div>
                        <label className="label">Reorder qty</label>
                        <input type="number" step="0.001" min="0" className="input" value={form.data.reorder_quantity} onChange={e => form.setData('reorder_quantity', e.target.value as unknown as number)} />
                    </div>
                    <div>
                        <label className="label">Weight</label>
                        <input type="number" step="0.001" min="0" className="input" value={form.data.weight} onChange={e => form.setData('weight', e.target.value as unknown as number)} />
                    </div>
                    <div>
                        <label className="label">Barcode</label>
                        <input className="input" value={form.data.barcode} onChange={e => form.setData('barcode', e.target.value)} />
                    </div>
                </div>

                <div className="grid grid-cols-2 gap-3">
                    <div>
                        <label className="label">Manufacturer</label>
                        <input className="input" value={form.data.manufacturer} onChange={e => form.setData('manufacturer', e.target.value)} />
                    </div>
                    <div>
                        <label className="label">Mfr part number (MPN)</label>
                        <input className="input" value={form.data.mpn} onChange={e => form.setData('mpn', e.target.value)} />
                    </div>
                </div>

                <div>
                    <label className="label">Description</label>
                    <textarea className="input min-h-[64px]" value={form.data.description} onChange={e => form.setData('description', e.target.value)} />
                </div>

                <div className="flex flex-wrap gap-5 pt-1">
                    <label className="flex items-center gap-2 text-sm text-foreground">
                        <input type="checkbox" checked={form.data.is_active} onChange={e => form.setData('is_active', e.target.checked)} /> Active
                    </label>
                    <label className="flex items-center gap-2 text-sm text-foreground">
                        <input type="checkbox" checked={form.data.track_inventory} onChange={e => form.setData('track_inventory', e.target.checked)} /> Track stock
                    </label>
                    <label className="flex items-center gap-2 text-sm text-foreground">
                        <input type="checkbox" checked={form.data.is_serialized} onChange={e => form.setData('is_serialized', e.target.checked)} /> Serialized
                    </label>
                </div>
            </form>
        </Modal>
    );
}
