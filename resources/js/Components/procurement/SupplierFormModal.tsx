import { useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import { Modal } from '@/Components/ui/Modal';
import { Button } from '@/Components/ui/Button';
import { Select } from '@/Components/ui/Select';

export interface EditableSupplier {
    id: number;
    code?: string;
    name?: string;
    category?: string | null;
    status?: string;
    email?: string | null;
    phone?: string | null;
    website?: string | null;
    address_line1?: string | null;
    city?: string | null;
    state?: string | null;
    postal_code?: string | null;
    country?: string | null;
    payment_terms?: string | null;
    currency?: string;
    tax_id?: string | null;
    lead_time_days?: number | null;
    rating?: number | null;
    notes?: string | null;
}

interface Props {
    open: boolean;
    onClose: () => void;
    supplier?: EditableSupplier | null;
    statuses: { value: string; label: string }[];
}

export function SupplierFormModal({ open, onClose, supplier, statuses }: Props) {
    const isEdit = !!supplier;
    const form = useForm({
        code: supplier?.code ?? '',
        name: supplier?.name ?? '',
        category: supplier?.category ?? '',
        status: supplier?.status ?? 'active',
        email: supplier?.email ?? '',
        phone: supplier?.phone ?? '',
        website: supplier?.website ?? '',
        address_line1: supplier?.address_line1 ?? '',
        city: supplier?.city ?? '',
        state: supplier?.state ?? '',
        postal_code: supplier?.postal_code ?? '',
        country: supplier?.country ?? '',
        payment_terms: supplier?.payment_terms ?? '',
        currency: supplier?.currency ?? 'USD',
        tax_id: supplier?.tax_id ?? '',
        lead_time_days: supplier?.lead_time_days ?? '',
        rating: supplier?.rating ?? '',
        notes: supplier?.notes ?? '',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        const opts = { preserveScroll: true, onSuccess: () => { if (!isEdit) form.reset(); onClose(); } };
        if (isEdit) form.put(`/procurement/suppliers/${supplier!.id}`, opts);
        else form.post('/procurement/suppliers', opts);
    };

    const err = (k: keyof typeof form.data) => form.errors[k as keyof typeof form.errors];

    return (
        <Modal
            open={open}
            onClose={onClose}
            size="lg"
            title={isEdit ? 'Edit Supplier' : 'Add Supplier'}
            footer={
                <>
                    <Button variant="ghost" onClick={onClose}>Cancel</Button>
                    <Button onClick={submit as unknown as () => void} disabled={form.processing}>
                        {form.processing ? 'Saving…' : isEdit ? 'Save Changes' : 'Add Supplier'}
                    </Button>
                </>
            }
        >
            <form onSubmit={submit} className="space-y-4">
                <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <div>
                        <label className="label">Code *</label>
                        <input className="input" value={form.data.code} onChange={e => form.setData('code', e.target.value)} autoFocus />
                        {err('code') && <p className="mt-1 text-xs text-destructive">{err('code')}</p>}
                    </div>
                    <div className="sm:col-span-2">
                        <label className="label">Name *</label>
                        <input className="input" value={form.data.name} onChange={e => form.setData('name', e.target.value)} />
                        {err('name') && <p className="mt-1 text-xs text-destructive">{err('name')}</p>}
                    </div>
                </div>
                <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <div>
                        <label className="label">Category</label>
                        <input className="input" value={form.data.category} onChange={e => form.setData('category', e.target.value)} />
                    </div>
                    <div>
                        <label className="label">Status</label>
                        <Select className="w-full" value={form.data.status} onChange={v => form.setData('status', v)} options={statuses} />
                    </div>
                    <div>
                        <label className="label">Payment terms</label>
                        <input className="input" value={form.data.payment_terms} onChange={e => form.setData('payment_terms', e.target.value)} placeholder="Net 30" />
                    </div>
                </div>
                <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <div>
                        <label className="label">Email</label>
                        <input type="email" className="input" value={form.data.email} onChange={e => form.setData('email', e.target.value)} />
                        {err('email') && <p className="mt-1 text-xs text-destructive">{err('email')}</p>}
                    </div>
                    <div>
                        <label className="label">Phone</label>
                        <input className="input" value={form.data.phone} onChange={e => form.setData('phone', e.target.value)} />
                    </div>
                    <div>
                        <label className="label">Website</label>
                        <input className="input" value={form.data.website} onChange={e => form.setData('website', e.target.value)} />
                    </div>
                </div>
                <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <div>
                        <label className="label">Currency</label>
                        <input className="input uppercase" maxLength={3} value={form.data.currency} onChange={e => form.setData('currency', e.target.value.toUpperCase())} />
                    </div>
                    <div>
                        <label className="label">Lead time (days)</label>
                        <input type="number" min="0" className="input" value={form.data.lead_time_days} onChange={e => form.setData('lead_time_days', e.target.value as unknown as number)} />
                    </div>
                    <div>
                        <label className="label">Rating (1–5)</label>
                        <input type="number" min="1" max="5" className="input" value={form.data.rating} onChange={e => form.setData('rating', e.target.value as unknown as number)} />
                    </div>
                    <div>
                        <label className="label">Tax ID</label>
                        <input className="input" value={form.data.tax_id} onChange={e => form.setData('tax_id', e.target.value)} />
                    </div>
                </div>
                <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <div className="sm:col-span-2">
                        <label className="label">Address</label>
                        <input className="input" value={form.data.address_line1} onChange={e => form.setData('address_line1', e.target.value)} />
                    </div>
                    <div><label className="label">City</label><input className="input" value={form.data.city} onChange={e => form.setData('city', e.target.value)} /></div>
                    <div><label className="label">State</label><input className="input" value={form.data.state} onChange={e => form.setData('state', e.target.value)} /></div>
                </div>
                <div>
                    <label className="label">Notes</label>
                    <textarea className="input min-h-[56px]" value={form.data.notes} onChange={e => form.setData('notes', e.target.value)} />
                </div>
            </form>
        </Modal>
    );
}
