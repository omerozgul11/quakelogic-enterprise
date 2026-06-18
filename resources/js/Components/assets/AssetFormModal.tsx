import { useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import { Modal } from '@/Components/ui/Modal';
import { Button } from '@/Components/ui/Button';
import { Select } from '@/Components/ui/Select';

export interface EditableAsset {
    id: number;
    asset_tag?: string;
    name?: string;
    serial_number?: string | null;
    status?: string;
    category?: string | null;
    location?: string | null;
    condition?: string | null;
    inventory_product_id?: number | null;
    company_id?: number | null;
    assigned_to?: number | null;
    purchase_cost?: number | null;
    current_value?: number | null;
    currency?: string;
    purchased_at?: string | null;
    warranty_expires_at?: string | null;
    deployed_at?: string | null;
    notes?: string | null;
}

interface FormData {
    products: { id: number; sku: string; name: string }[];
    companies: { id: number; name: string }[];
    users: { id: number; name: string }[];
}

interface Props {
    open: boolean;
    onClose: () => void;
    asset?: EditableAsset | null;
    statuses: { value: string; label: string }[];
    form: FormData;
    defaultTag?: string;
}

const CONDITIONS = [
    { value: 'new', label: 'New' }, { value: 'good', label: 'Good' },
    { value: 'fair', label: 'Fair' }, { value: 'poor', label: 'Poor' },
];

export function AssetFormModal({ open, onClose, asset, statuses, form: opts, defaultTag }: Props) {
    const isEdit = !!asset;
    const form = useForm({
        asset_tag: asset?.asset_tag ?? defaultTag ?? '',
        name: asset?.name ?? '',
        serial_number: asset?.serial_number ?? '',
        status: asset?.status ?? 'in_stock',
        category: asset?.category ?? '',
        location: asset?.location ?? '',
        condition: asset?.condition ?? 'good',
        inventory_product_id: asset?.inventory_product_id ? String(asset.inventory_product_id) : '',
        company_id: asset?.company_id ? String(asset.company_id) : '',
        assigned_to: asset?.assigned_to ? String(asset.assigned_to) : '',
        purchase_cost: asset?.purchase_cost ?? '',
        current_value: asset?.current_value ?? '',
        currency: asset?.currency ?? 'USD',
        purchased_at: asset?.purchased_at ?? '',
        warranty_expires_at: asset?.warranty_expires_at ?? '',
        deployed_at: asset?.deployed_at ?? '',
        notes: asset?.notes ?? '',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        const o = { preserveScroll: true, onSuccess: () => { if (!isEdit) form.reset(); onClose(); } };
        if (isEdit) form.put(`/assets/registry/${asset!.id}`, o);
        else form.post('/assets/registry', o);
    };
    const err = (k: keyof typeof form.data) => form.errors[k as keyof typeof form.errors];

    return (
        <Modal
            open={open}
            onClose={onClose}
            size="lg"
            title={isEdit ? 'Edit Asset' : 'Add Asset'}
            footer={
                <>
                    <Button variant="ghost" onClick={onClose}>Cancel</Button>
                    <Button onClick={submit as unknown as () => void} disabled={form.processing}>
                        {form.processing ? 'Saving…' : isEdit ? 'Save Changes' : 'Add Asset'}
                    </Button>
                </>
            }
        >
            <form onSubmit={submit} className="space-y-4">
                <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <div>
                        <label className="label">Asset tag *</label>
                        <input className="input font-mono" value={form.data.asset_tag} onChange={e => form.setData('asset_tag', e.target.value)} />
                        {err('asset_tag') && <p className="mt-1 text-xs text-destructive">{err('asset_tag')}</p>}
                    </div>
                    <div className="sm:col-span-2">
                        <label className="label">Name *</label>
                        <input className="input" value={form.data.name} onChange={e => form.setData('name', e.target.value)} autoFocus />
                        {err('name') && <p className="mt-1 text-xs text-destructive">{err('name')}</p>}
                    </div>
                </div>
                <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <div><label className="label">Serial #</label><input className="input" value={form.data.serial_number} onChange={e => form.setData('serial_number', e.target.value)} /></div>
                    <div><label className="label">Status</label><Select className="w-full" value={form.data.status} onChange={v => form.setData('status', v)} options={statuses} /></div>
                    <div><label className="label">Category</label><input className="input" value={form.data.category} onChange={e => form.setData('category', e.target.value)} /></div>
                    <div><label className="label">Condition</label><Select className="w-full" value={form.data.condition} onChange={v => form.setData('condition', v)} options={CONDITIONS} /></div>
                </div>
                <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <div>
                        <label className="label">Product type</label>
                        <Select className="w-full" value={form.data.inventory_product_id} placeholder="— None —" onChange={v => form.setData('inventory_product_id', v)} options={opts.products.map(p => ({ value: String(p.id), label: `${p.sku} · ${p.name}` }))} />
                    </div>
                    <div>
                        <label className="label">Deployed at (customer)</label>
                        <Select className="w-full" value={form.data.company_id} placeholder="— Internal —" onChange={v => form.setData('company_id', v)} options={opts.companies.map(c => ({ value: String(c.id), label: c.name }))} />
                    </div>
                    <div>
                        <label className="label">Assigned to</label>
                        <Select className="w-full" value={form.data.assigned_to} placeholder="— Unassigned —" onChange={v => form.setData('assigned_to', v)} options={opts.users.map(u => ({ value: String(u.id), label: u.name }))} />
                    </div>
                </div>
                <div>
                    <label className="label">Location</label>
                    <input className="input" value={form.data.location} onChange={e => form.setData('location', e.target.value)} placeholder="Site / facility" />
                </div>
                <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <div><label className="label">Purchase cost</label><input type="number" step="0.01" min="0" className="input" value={form.data.purchase_cost} onChange={e => form.setData('purchase_cost', e.target.value as unknown as number)} /></div>
                    <div><label className="label">Current value</label><input type="number" step="0.01" min="0" className="input" value={form.data.current_value} onChange={e => form.setData('current_value', e.target.value as unknown as number)} /></div>
                    <div><label className="label">Purchased</label><input type="date" className="input" value={form.data.purchased_at} onChange={e => form.setData('purchased_at', e.target.value)} /></div>
                    <div><label className="label">Warranty ends</label><input type="date" className="input" value={form.data.warranty_expires_at} onChange={e => form.setData('warranty_expires_at', e.target.value)} /></div>
                </div>
                <div>
                    <label className="label">Notes</label>
                    <textarea className="input min-h-[56px]" value={form.data.notes} onChange={e => form.setData('notes', e.target.value)} />
                </div>
            </form>
        </Modal>
    );
}
