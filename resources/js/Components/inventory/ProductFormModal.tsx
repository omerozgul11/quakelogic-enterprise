import { useForm } from '@inertiajs/react';
import { ChangeEvent, FormEvent, useRef, useState } from 'react';
import { Modal } from '@/Components/ui/Modal';
import { Button } from '@/Components/ui/Button';
import { Select } from '@/Components/ui/Select';
import { Checkbox } from '@/Components/ui/Checkbox';
import { generateSku, generateBarcode } from '@/Lib/utils';
import { Image as ImageIcon, Wand2 } from 'lucide-react';

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
    image_url?: string | null;
}

interface Props {
    open: boolean;
    onClose: () => void;
    product?: EditableProduct | null;
    types: { value: string; label: string }[];
    currencies: { value: string; label: string; symbol?: string }[];
}

export function ProductFormModal({ open, onClose, product, types, currencies }: Props) {
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
        image: null as File | null,
        remove_image: false as boolean,
    });

    const fileRef = useRef<HTMLInputElement>(null);
    const [preview, setPreview] = useState<string | null>(product?.image_url ?? null);

    const pickImage = (e: ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (!file) return;
        form.setData('image', file);
        form.setData('remove_image', false);
        setPreview(URL.createObjectURL(file));
    };
    const clearImage = () => {
        form.setData('image', null);
        form.setData('remove_image', true);
        setPreview(null);
        if (fileRef.current) fileRef.current.value = '';
    };

    const submit = (e: FormEvent) => {
        e.preventDefault();
        const opts = { preserveScroll: true, forceFormData: true, onSuccess: () => { if (!isEdit) form.reset(); onClose(); } };
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
                        <div className="flex items-center justify-between">
                            <label className="label">SKU *</label>
                            <button type="button" onClick={() => form.setData('sku', generateSku())} className="mb-1.5 inline-flex items-center gap-1 text-xs font-semibold text-primary transition-colors hover:text-primary/80">
                                <Wand2 className="h-3.5 w-3.5" /> Generate
                            </button>
                        </div>
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
                        <Select className="w-full" value={form.data.currency} onChange={v => form.setData('currency', v)}
                            options={currencies.map(c => ({ value: c.value, label: c.value }))} />
                        {err('currency') && <p className="mt-1 text-xs text-destructive">{err('currency')}</p>}
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
                        <div className="flex items-center justify-between">
                            <label className="label">Barcode</label>
                            <button type="button" onClick={() => form.setData('barcode', generateBarcode())} className="mb-1.5 inline-flex items-center gap-1 text-xs font-semibold text-primary transition-colors hover:text-primary/80">
                                <Wand2 className="h-3.5 w-3.5" /> Generate
                            </button>
                        </div>
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

                <div>
                    <label className="label">Image</label>
                    <div className="flex items-center gap-3">
                        <div className="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-border bg-secondary">
                            {preview
                                ? <img src={preview} alt="Product" className="h-full w-full object-cover" />
                                : <ImageIcon className="h-6 w-6 text-muted-foreground" />}
                        </div>
                        <div className="flex flex-col gap-1.5">
                            <input ref={fileRef} type="file" accept="image/jpeg,image/png,image/webp,image/gif" className="hidden" onChange={pickImage} />
                            <div className="flex items-center gap-3">
                                <Button type="button" variant="secondary" size="sm" onClick={() => fileRef.current?.click()}>
                                    {preview ? 'Change image' : 'Upload image'}
                                </Button>
                                {preview && <button type="button" onClick={clearImage} className="text-xs font-medium text-destructive hover:underline">Remove</button>}
                            </div>
                            <p className="text-xs text-muted-foreground">JPEG, PNG, WEBP or GIF · up to 5 MB</p>
                        </div>
                    </div>
                    {err('image') && <p className="mt-1 text-xs text-destructive">{err('image')}</p>}
                </div>

                <div className="flex flex-wrap gap-x-6 gap-y-3 pt-1">
                    <div className="flex items-center gap-2 text-sm text-foreground">
                        <Checkbox checked={form.data.is_active} onChange={v => form.setData('is_active', v)} ariaLabel="Active" />
                        <span className="cursor-pointer" onClick={() => form.setData('is_active', !form.data.is_active)}>Active</span>
                    </div>
                    <div className="flex items-center gap-2 text-sm text-foreground">
                        <Checkbox checked={form.data.track_inventory} onChange={v => form.setData('track_inventory', v)} ariaLabel="Track stock" />
                        <span className="cursor-pointer" onClick={() => form.setData('track_inventory', !form.data.track_inventory)}>Track stock</span>
                    </div>
                    <div className="flex items-center gap-2 text-sm text-foreground">
                        <Checkbox checked={form.data.is_serialized} onChange={v => form.setData('is_serialized', v)} ariaLabel="Serialized" />
                        <span className="cursor-pointer" onClick={() => form.setData('is_serialized', !form.data.is_serialized)}>Serialized</span>
                    </div>
                </div>
            </form>
        </Modal>
    );
}
