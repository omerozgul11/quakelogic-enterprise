import { useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import { Modal } from '@/Components/ui/Modal';
import { Button } from '@/Components/ui/Button';
import { Select } from '@/Components/ui/Select';

export interface EditableContact {
    id: number;
    first_name?: string;
    last_name?: string;
    title?: string | null;
    department?: string | null;
    email?: string | null;
    phone?: string | null;
    mobile?: string | null;
    linkedin_url?: string | null;
    company_id?: number | null;
    is_decision_maker?: boolean;
    is_key_contact?: boolean;
    notes?: string | null;
}

interface Props {
    open: boolean;
    onClose: () => void;
    contact?: EditableContact | null;
    companies: Array<{ id: number; name: string }>;
}

export function CrmContactFormModal({ open, onClose, contact, companies }: Props) {
    const isEdit = !!contact;
    const form = useForm({
        first_name: contact?.first_name ?? '',
        last_name: contact?.last_name ?? '',
        title: contact?.title ?? '',
        department: contact?.department ?? '',
        email: contact?.email ?? '',
        phone: contact?.phone ?? '',
        mobile: contact?.mobile ?? '',
        linkedin_url: contact?.linkedin_url ?? '',
        company_id: contact?.company_id ? String(contact.company_id) : '',
        is_decision_maker: contact?.is_decision_maker ?? false,
        is_key_contact: contact?.is_key_contact ?? false,
        notes: contact?.notes ?? '',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.transform(data => ({ ...data, company_id: data.company_id || null }));
        const opts = { preserveScroll: true, onSuccess: () => { form.reset(); onClose(); } };
        if (isEdit) form.put(`/crm/contacts/${contact!.id}`, opts);
        else form.post('/crm/contacts', opts);
    };

    const err = (k: keyof typeof form.data) => form.errors[k as keyof typeof form.errors];

    return (
        <Modal
            open={open}
            onClose={onClose}
            title={isEdit ? 'Edit Contact' : 'Add Contact'}
            description={isEdit ? 'Update this contact’s details.' : 'Add a contact to your CRM.'}
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
                <div className="grid grid-cols-2 gap-3">
                    <div>
                        <label className="label">First name *</label>
                        <input className="input" value={form.data.first_name} onChange={e => form.setData('first_name', e.target.value)} autoFocus />
                        {err('first_name') && <p className="mt-1 text-xs text-destructive">{err('first_name')}</p>}
                    </div>
                    <div>
                        <label className="label">Last name *</label>
                        <input className="input" value={form.data.last_name} onChange={e => form.setData('last_name', e.target.value)} />
                        {err('last_name') && <p className="mt-1 text-xs text-destructive">{err('last_name')}</p>}
                    </div>
                </div>
                <div className="grid grid-cols-2 gap-3">
                    <div>
                        <label className="label">Title</label>
                        <input className="input" value={form.data.title} onChange={e => form.setData('title', e.target.value)} />
                    </div>
                    <div>
                        <label className="label">Company</label>
                        <Select
                            className="w-full"
                            value={form.data.company_id}
                            onChange={v => form.setData('company_id', v)}
                            placeholder="— None —"
                            options={companies.map(c => ({ value: String(c.id), label: c.name }))}
                        />
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
                <div className="grid grid-cols-2 gap-3">
                    <div>
                        <label className="label">Mobile</label>
                        <input className="input" value={form.data.mobile} onChange={e => form.setData('mobile', e.target.value)} />
                    </div>
                    <div>
                        <label className="label">LinkedIn URL</label>
                        <input className="input" value={form.data.linkedin_url} onChange={e => form.setData('linkedin_url', e.target.value)} placeholder="https://…" />
                        {err('linkedin_url') && <p className="mt-1 text-xs text-destructive">{err('linkedin_url')}</p>}
                    </div>
                </div>
                <div className="flex flex-wrap gap-5">
                    <label className="flex items-center gap-2 text-sm text-foreground">
                        <input type="checkbox" className="h-4 w-4 rounded border-input" checked={form.data.is_decision_maker} onChange={e => form.setData('is_decision_maker', e.target.checked)} />
                        Decision maker
                    </label>
                    <label className="flex items-center gap-2 text-sm text-foreground">
                        <input type="checkbox" className="h-4 w-4 rounded border-input" checked={form.data.is_key_contact} onChange={e => form.setData('is_key_contact', e.target.checked)} />
                        Key contact
                    </label>
                </div>
                <div>
                    <label className="label">Notes</label>
                    <textarea className="input min-h-[72px]" value={form.data.notes} onChange={e => form.setData('notes', e.target.value)} />
                </div>
            </form>
        </Modal>
    );
}
