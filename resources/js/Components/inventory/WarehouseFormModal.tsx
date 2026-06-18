import { useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import { Modal } from '@/Components/ui/Modal';
import { Button } from '@/Components/ui/Button';
import { Select } from '@/Components/ui/Select';

export interface EditableWarehouse {
    id: number;
    code?: string;
    name?: string;
    type?: string;
    address_line1?: string | null;
    city?: string | null;
    state?: string | null;
    postal_code?: string | null;
    country?: string | null;
    is_default?: boolean;
    is_active?: boolean;
    notes?: string | null;
}

interface Props {
    open: boolean;
    onClose: () => void;
    warehouse?: EditableWarehouse | null;
}

const TYPES = [
    { value: 'main', label: 'Main' },
    { value: 'transit', label: 'Transit' },
    { value: 'supplier', label: 'Supplier' },
    { value: 'customer', label: 'Customer' },
    { value: 'virtual', label: 'Virtual' },
];

export function WarehouseFormModal({ open, onClose, warehouse }: Props) {
    const isEdit = !!warehouse;
    const form = useForm({
        code: warehouse?.code ?? '',
        name: warehouse?.name ?? '',
        type: warehouse?.type ?? 'main',
        address_line1: warehouse?.address_line1 ?? '',
        city: warehouse?.city ?? '',
        state: warehouse?.state ?? '',
        postal_code: warehouse?.postal_code ?? '',
        country: warehouse?.country ?? '',
        is_default: warehouse?.is_default ?? false,
        is_active: warehouse?.is_active ?? true,
        notes: warehouse?.notes ?? '',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        const opts = { preserveScroll: true, onSuccess: () => { if (!isEdit) form.reset(); onClose(); } };
        if (isEdit) form.put(`/inventory/warehouses/${warehouse!.id}`, opts);
        else form.post('/inventory/warehouses', opts);
    };

    const err = (k: keyof typeof form.data) => form.errors[k as keyof typeof form.errors];

    return (
        <Modal
            open={open}
            onClose={onClose}
            title={isEdit ? 'Edit Warehouse' : 'Add Warehouse'}
            footer={
                <>
                    <Button variant="ghost" onClick={onClose}>Cancel</Button>
                    <Button onClick={submit as unknown as () => void} disabled={form.processing}>
                        {form.processing ? 'Saving…' : isEdit ? 'Save Changes' : 'Add Warehouse'}
                    </Button>
                </>
            }
        >
            <form onSubmit={submit} className="space-y-4">
                <div className="grid grid-cols-3 gap-3">
                    <div>
                        <label className="label">Code *</label>
                        <input className="input" value={form.data.code} onChange={e => form.setData('code', e.target.value)} autoFocus />
                        {err('code') && <p className="mt-1 text-xs text-destructive">{err('code')}</p>}
                    </div>
                    <div className="col-span-2">
                        <label className="label">Name *</label>
                        <input className="input" value={form.data.name} onChange={e => form.setData('name', e.target.value)} />
                        {err('name') && <p className="mt-1 text-xs text-destructive">{err('name')}</p>}
                    </div>
                </div>
                <div>
                    <label className="label">Type</label>
                    <Select className="w-full" value={form.data.type} onChange={v => form.setData('type', v)} options={TYPES} />
                </div>
                <div>
                    <label className="label">Address</label>
                    <input className="input" value={form.data.address_line1} onChange={e => form.setData('address_line1', e.target.value)} />
                </div>
                <div className="grid grid-cols-3 gap-3">
                    <div>
                        <label className="label">City</label>
                        <input className="input" value={form.data.city} onChange={e => form.setData('city', e.target.value)} />
                    </div>
                    <div>
                        <label className="label">State</label>
                        <input className="input" value={form.data.state} onChange={e => form.setData('state', e.target.value)} />
                    </div>
                    <div>
                        <label className="label">Postal code</label>
                        <input className="input" value={form.data.postal_code} onChange={e => form.setData('postal_code', e.target.value)} />
                    </div>
                </div>
                <div>
                    <label className="label">Notes</label>
                    <textarea className="input min-h-[56px]" value={form.data.notes} onChange={e => form.setData('notes', e.target.value)} />
                </div>
                <div className="flex flex-wrap gap-5 pt-1">
                    <label className="flex items-center gap-2 text-sm text-foreground">
                        <input type="checkbox" checked={form.data.is_default} onChange={e => form.setData('is_default', e.target.checked)} /> Default warehouse
                    </label>
                    <label className="flex items-center gap-2 text-sm text-foreground">
                        <input type="checkbox" checked={form.data.is_active} onChange={e => form.setData('is_active', e.target.checked)} /> Active
                    </label>
                </div>
            </form>
        </Modal>
    );
}
