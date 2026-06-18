import { useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import { Modal } from '@/Components/ui/Modal';
import { Button } from '@/Components/ui/Button';

export interface EditableSupplierContact {
    id: number;
    name?: string;
    title?: string | null;
    email?: string | null;
    phone?: string | null;
    is_primary?: boolean;
}

interface Props {
    open: boolean;
    onClose: () => void;
    supplierId: number;
    contact?: EditableSupplierContact | null;
}

export function SupplierContactModal({ open, onClose, supplierId, contact }: Props) {
    const isEdit = !!contact;
    const form = useForm({
        name: contact?.name ?? '',
        title: contact?.title ?? '',
        email: contact?.email ?? '',
        phone: contact?.phone ?? '',
        is_primary: contact?.is_primary ?? false,
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        const opts = { preserveScroll: true, onSuccess: () => { if (!isEdit) form.reset(); onClose(); } };
        if (isEdit) form.put(`/procurement/suppliers/${supplierId}/contacts/${contact!.id}`, opts);
        else form.post(`/procurement/suppliers/${supplierId}/contacts`, opts);
    };

    const err = (k: keyof typeof form.data) => form.errors[k as keyof typeof form.errors];

    return (
        <Modal
            open={open}
            onClose={onClose}
            title={isEdit ? 'Edit Contact' : 'Add Contact'}
            footer={
                <>
                    <Button variant="ghost" onClick={onClose}>Cancel</Button>
                    <Button onClick={submit as unknown as () => void} disabled={form.processing}>
                        {form.processing ? 'Saving…' : isEdit ? 'Save' : 'Add Contact'}
                    </Button>
                </>
            }
        >
            <form onSubmit={submit} className="space-y-4">
                <div className="grid grid-cols-2 gap-3">
                    <div>
                        <label className="label">Name *</label>
                        <input className="input" value={form.data.name} onChange={e => form.setData('name', e.target.value)} autoFocus />
                        {err('name') && <p className="mt-1 text-xs text-destructive">{err('name')}</p>}
                    </div>
                    <div>
                        <label className="label">Title</label>
                        <input className="input" value={form.data.title} onChange={e => form.setData('title', e.target.value)} />
                    </div>
                </div>
                <div className="grid grid-cols-2 gap-3">
                    <div>
                        <label className="label">Email</label>
                        <input type="email" className="input" value={form.data.email} onChange={e => form.setData('email', e.target.value)} />
                        {err('email') && <p className="mt-1 text-xs text-destructive">{err('email')}</p>}
                    </div>
                    <div>
                        <label className="label">Phone</label>
                        <input className="input" value={form.data.phone} onChange={e => form.setData('phone', e.target.value)} />
                    </div>
                </div>
                <label className="flex items-center gap-2 text-sm text-foreground">
                    <input type="checkbox" checked={form.data.is_primary} onChange={e => form.setData('is_primary', e.target.checked)} /> Primary contact
                </label>
            </form>
        </Modal>
    );
}
