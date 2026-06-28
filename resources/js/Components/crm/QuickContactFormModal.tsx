import { useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import { Modal } from '@/Components/ui/Modal';
import { Button } from '@/Components/ui/Button';
import { Select } from '@/Components/ui/Select';

export interface EditableQuickContact {
    id: number;
    name?: string;
    organization_name?: string | null;
    category?: string;
    phone?: string | null;
    extension?: string | null;
    email?: string | null;
    website?: string | null;
    notes?: string | null;
    is_pinned?: boolean;
}

interface Props {
    open: boolean;
    onClose: () => void;
    contact?: EditableQuickContact | null;
    categories: Array<{ value: string; label: string; color: string }>;
}

export function QuickContactFormModal({ open, onClose, contact, categories }: Props) {
    const isEdit = !!contact;
    const form = useForm({
        name: contact?.name ?? '',
        organization_name: contact?.organization_name ?? '',
        category: contact?.category ?? 'other',
        phone: contact?.phone ?? '',
        extension: contact?.extension ?? '',
        email: contact?.email ?? '',
        website: contact?.website ?? '',
        notes: contact?.notes ?? '',
        is_pinned: contact?.is_pinned ?? false,
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        const opts = { preserveScroll: true, onSuccess: () => { form.reset(); onClose(); } };
        if (isEdit) form.put(`/crm/quick-contacts/${contact!.id}`, opts);
        else form.post('/crm/quick-contacts', opts);
    };

    const err = (k: keyof typeof form.data) => form.errors[k as keyof typeof form.errors];

    return (
        <Modal
            open={open}
            onClose={onClose}
            title={isEdit ? 'Edit Quick Contact' : 'Add Quick Contact'}
            description={isEdit ? 'Update this reference contact.' : 'Save a frequently-dialed number for quick reference.'}
            footer={
                <>
                    <Button variant="ghost" onClick={onClose}>Cancel</Button>
                    <Button onClick={submit as unknown as () => void} disabled={form.processing}>
                        {form.processing ? 'Saving…' : isEdit ? 'Save Changes' : 'Add Contact'}
                    </Button>
                </>
            }
        >
            <form onSubmit={submit} className="space-y-4">
                <div>
                    <label className="label">Name *</label>
                    <input className="input" value={form.data.name} onChange={e => form.setData('name', e.target.value)} placeholder="Chase Wire Transfer Department" autoFocus />
                    {err('name') && <p className="mt-1 text-xs text-destructive">{err('name')}</p>}
                </div>
                <div className="grid grid-cols-2 gap-3">
                    <div>
                        <label className="label">Organization</label>
                        <input className="input" value={form.data.organization_name} onChange={e => form.setData('organization_name', e.target.value)} placeholder="Chase Bank" />
                    </div>
                    <div>
                        <label className="label">Category *</label>
                        <Select
                            className="w-full"
                            value={form.data.category}
                            onChange={v => form.setData('category', v)}
                            options={categories.map(c => ({ value: c.value, label: c.label }))}
                        />
                        {err('category') && <p className="mt-1 text-xs text-destructive">{err('category')}</p>}
                    </div>
                </div>
                <div className="grid grid-cols-3 gap-3">
                    <div className="col-span-2">
                        <label className="label">Phone</label>
                        <input className="input" value={form.data.phone} onChange={e => form.setData('phone', e.target.value)} placeholder="855-536-1269" />
                    </div>
                    <div>
                        <label className="label">Ext.</label>
                        <input className="input" value={form.data.extension} onChange={e => form.setData('extension', e.target.value)} placeholder="123" />
                    </div>
                </div>
                <div className="grid grid-cols-2 gap-3">
                    <div>
                        <label className="label">Email</label>
                        <input type="email" className="input" value={form.data.email} onChange={e => form.setData('email', e.target.value)} />
                        {err('email') && <p className="mt-1 text-xs text-destructive">{err('email')}</p>}
                    </div>
                    <div>
                        <label className="label">Website</label>
                        <input className="input" value={form.data.website} onChange={e => form.setData('website', e.target.value)} placeholder="https://…" />
                    </div>
                </div>
                <div>
                    <label className="label">Notes</label>
                    <textarea className="input min-h-[64px]" value={form.data.notes} onChange={e => form.setData('notes', e.target.value)} placeholder="Hours, reference codes, who to ask for…" />
                    {err('notes') && <p className="mt-1 text-xs text-destructive">{err('notes')}</p>}
                </div>
                <label className="flex items-center gap-2 text-sm text-foreground">
                    <input type="checkbox" className="h-4 w-4 rounded border-input" checked={form.data.is_pinned} onChange={e => form.setData('is_pinned', e.target.checked)} />
                    Pin to top
                </label>
            </form>
        </Modal>
    );
}
