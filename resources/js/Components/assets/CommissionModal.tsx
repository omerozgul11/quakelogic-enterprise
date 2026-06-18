import { useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import { Modal } from '@/Components/ui/Modal';
import { Button } from '@/Components/ui/Button';
import { Select } from '@/Components/ui/Select';

interface FormData {
    products: { id: number; sku: string; name: string }[];
    warehouses: { id: number; name: string; code: string }[];
    companies: { id: number; name: string }[];
    users: { id: number; name: string }[];
}

interface Props {
    open: boolean;
    onClose: () => void;
    statuses: { value: string; label: string }[];
    form: FormData;
}

export function CommissionModal({ open, onClose, statuses, form: opts }: Props) {
    const form = useForm({
        inventory_product_id: '',
        inventory_warehouse_id: '',
        name: '',
        serial_number: '',
        status: 'deployed',
        location: '',
        company_id: '',
        assigned_to: '',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post('/assets/registry/commission', { preserveScroll: true, onSuccess: () => { form.reset(); onClose(); } });
    };
    const err = (k: keyof typeof form.data) => form.errors[k as keyof typeof form.errors];

    return (
        <Modal
            open={open}
            onClose={onClose}
            title="Commission from Inventory"
            description="Draw one unit out of stock and register it as a tracked asset."
            footer={
                <>
                    <Button variant="ghost" onClick={onClose}>Cancel</Button>
                    <Button variant="success" onClick={submit as unknown as () => void} disabled={form.processing}>
                        {form.processing ? 'Commissioning…' : 'Commission asset'}
                    </Button>
                </>
            }
        >
            <form onSubmit={submit} className="space-y-4">
                <div>
                    <label className="label">Product *</label>
                    <Select className="w-full" value={form.data.inventory_product_id} placeholder="Select stocked product…" onChange={v => form.setData('inventory_product_id', v)} options={opts.products.map(p => ({ value: String(p.id), label: `${p.sku} · ${p.name}` }))} />
                    {err('inventory_product_id') && <p className="mt-1 text-xs text-destructive">{err('inventory_product_id')}</p>}
                </div>
                <div>
                    <label className="label">Draw from warehouse *</label>
                    <Select className="w-full" value={form.data.inventory_warehouse_id} placeholder="Select…" onChange={v => form.setData('inventory_warehouse_id', v)} options={opts.warehouses.map(w => ({ value: String(w.id), label: w.code ? `${w.name} (${w.code})` : w.name }))} />
                    {err('inventory_warehouse_id') && <p className="mt-1 text-xs text-destructive">{err('inventory_warehouse_id')}</p>}
                </div>
                <div className="grid grid-cols-2 gap-3">
                    <div><label className="label">Serial #</label><input className="input" value={form.data.serial_number} onChange={e => form.setData('serial_number', e.target.value)} /></div>
                    <div><label className="label">Status</label><Select className="w-full" value={form.data.status} onChange={v => form.setData('status', v)} options={statuses} /></div>
                </div>
                <div>
                    <label className="label">Name override</label>
                    <input className="input" value={form.data.name} onChange={e => form.setData('name', e.target.value)} placeholder="Defaults to the product name" />
                </div>
                <div>
                    <label className="label">Location</label>
                    <input className="input" value={form.data.location} onChange={e => form.setData('location', e.target.value)} placeholder="Deployment site" />
                </div>
                <div className="grid grid-cols-2 gap-3">
                    <div>
                        <label className="label">Customer site</label>
                        <Select className="w-full" value={form.data.company_id} placeholder="— Internal —" onChange={v => form.setData('company_id', v)} options={opts.companies.map(c => ({ value: String(c.id), label: c.name }))} />
                    </div>
                    <div>
                        <label className="label">Assigned to</label>
                        <Select className="w-full" value={form.data.assigned_to} placeholder="— Unassigned —" onChange={v => form.setData('assigned_to', v)} options={opts.users.map(u => ({ value: String(u.id), label: u.name }))} />
                    </div>
                </div>
            </form>
        </Modal>
    );
}
