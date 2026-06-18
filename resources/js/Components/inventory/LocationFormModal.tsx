import { useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import { Modal } from '@/Components/ui/Modal';
import { Button } from '@/Components/ui/Button';
import { Select } from '@/Components/ui/Select';

export interface EditableLocation {
    id: number;
    code?: string;
    name?: string | null;
    zone?: string | null;
    aisle?: string | null;
    rack?: string | null;
    shelf?: string | null;
    bin?: string | null;
    type?: string;
    is_active?: boolean;
}

interface Props {
    open: boolean;
    onClose: () => void;
    warehouseId: number;
    location?: EditableLocation | null;
}

const TYPES = [
    { value: 'bin', label: 'Bin' },
    { value: 'staging', label: 'Staging' },
    { value: 'receiving', label: 'Receiving' },
    { value: 'shipping', label: 'Shipping' },
    { value: 'quarantine', label: 'Quarantine' },
];

export function LocationFormModal({ open, onClose, warehouseId, location }: Props) {
    const isEdit = !!location;
    const form = useForm({
        code: location?.code ?? '',
        name: location?.name ?? '',
        zone: location?.zone ?? '',
        aisle: location?.aisle ?? '',
        rack: location?.rack ?? '',
        shelf: location?.shelf ?? '',
        bin: location?.bin ?? '',
        type: location?.type ?? 'bin',
        is_active: location?.is_active ?? true,
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        const opts = { preserveScroll: true, onSuccess: () => { if (!isEdit) form.reset(); onClose(); } };
        if (isEdit) form.put(`/inventory/warehouses/${warehouseId}/locations/${location!.id}`, opts);
        else form.post(`/inventory/warehouses/${warehouseId}/locations`, opts);
    };

    const err = (k: keyof typeof form.data) => form.errors[k as keyof typeof form.errors];

    return (
        <Modal
            open={open}
            onClose={onClose}
            title={isEdit ? 'Edit Location' : 'Add Location'}
            description="Bins / zones inside this warehouse."
            footer={
                <>
                    <Button variant="ghost" onClick={onClose}>Cancel</Button>
                    <Button onClick={submit as unknown as () => void} disabled={form.processing}>
                        {form.processing ? 'Saving…' : isEdit ? 'Save Changes' : 'Add Location'}
                    </Button>
                </>
            }
        >
            <form onSubmit={submit} className="space-y-4">
                <div className="grid grid-cols-3 gap-3">
                    <div>
                        <label className="label">Code *</label>
                        <input className="input" value={form.data.code} onChange={e => form.setData('code', e.target.value)} placeholder="A-01-02" autoFocus />
                        {err('code') && <p className="mt-1 text-xs text-destructive">{err('code')}</p>}
                    </div>
                    <div className="col-span-2">
                        <label className="label">Name</label>
                        <input className="input" value={form.data.name} onChange={e => form.setData('name', e.target.value)} />
                    </div>
                </div>
                <div className="grid grid-cols-2 gap-3 sm:grid-cols-5">
                    <div><label className="label">Zone</label><input className="input" value={form.data.zone} onChange={e => form.setData('zone', e.target.value)} /></div>
                    <div><label className="label">Aisle</label><input className="input" value={form.data.aisle} onChange={e => form.setData('aisle', e.target.value)} /></div>
                    <div><label className="label">Rack</label><input className="input" value={form.data.rack} onChange={e => form.setData('rack', e.target.value)} /></div>
                    <div><label className="label">Shelf</label><input className="input" value={form.data.shelf} onChange={e => form.setData('shelf', e.target.value)} /></div>
                    <div><label className="label">Bin</label><input className="input" value={form.data.bin} onChange={e => form.setData('bin', e.target.value)} /></div>
                </div>
                <div className="grid grid-cols-2 gap-3">
                    <div>
                        <label className="label">Type</label>
                        <Select className="w-full" value={form.data.type} onChange={v => form.setData('type', v)} options={TYPES} />
                    </div>
                    <label className="mt-6 flex items-center gap-2 text-sm text-foreground">
                        <input type="checkbox" checked={form.data.is_active} onChange={e => form.setData('is_active', e.target.checked)} /> Active
                    </label>
                </div>
            </form>
        </Modal>
    );
}
