import { useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import { Modal } from '@/Components/ui/Modal';
import { Button } from '@/Components/ui/Button';
import { Select } from '@/Components/ui/Select';

export interface EditableClient {
    id: number;
    name?: string;
    company_type?: string | null;
    industry?: string | null;
    cage_code?: string | null;
    website?: string | null;
    phone?: string | null;
    email?: string | null;
    address_line1?: string | null;
    city?: string | null;
    state?: string | null;
    notes?: string | null;
}

interface Props {
    open: boolean;
    onClose: () => void;
    client?: EditableClient | null;
}

const TYPES = ['client', 'prime', 'partner', 'vendor', 'subcontractor', 'teaming_partner', 'competitor'];

export function CrmClientFormModal({ open, onClose, client }: Props) {
    const isEdit = !!client;
    const form = useForm({
        name: client?.name ?? '',
        company_type: client?.company_type ?? '',
        industry: client?.industry ?? '',
        cage_code: client?.cage_code ?? '',
        website: client?.website ?? '',
        phone: client?.phone ?? '',
        email: client?.email ?? '',
        address_line1: client?.address_line1 ?? '',
        city: client?.city ?? '',
        state: client?.state ?? '',
        notes: client?.notes ?? '',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        const opts = { preserveScroll: true, onSuccess: () => { form.reset(); onClose(); } };
        if (isEdit) form.put(`/crm/clients/${client!.id}`, opts);
        else form.post('/crm/clients', opts);
    };

    const err = (k: keyof typeof form.data) => form.errors[k as keyof typeof form.errors];

    return (
        <Modal
            open={open}
            onClose={onClose}
            title={isEdit ? 'Edit Client' : 'Add Client'}
            description={isEdit ? 'Update this client’s details.' : 'Add a client to your CRM.'}
            footer={
                <>
                    <Button variant="ghost" onClick={onClose}>Cancel</Button>
                    <Button onClick={submit as unknown as () => void} disabled={form.processing}>
                        {form.processing ? 'Saving…' : isEdit ? 'Save Changes' : 'Add Client'}
                    </Button>
                </>
            }
        >
            <form onSubmit={submit} className="space-y-4">
                <div>
                    <label className="label">Client name *</label>
                    <input className="input" value={form.data.name} onChange={e => form.setData('name', e.target.value)} autoFocus />
                    {err('name') && <p className="mt-1 text-xs text-destructive">{err('name')}</p>}
                </div>
                <div className="grid grid-cols-2 gap-3">
                    <div>
                        <label className="label">Type</label>
                        <Select
                            className="w-full"
                            value={form.data.company_type}
                            onChange={v => form.setData('company_type', v)}
                            placeholder="— None —"
                            options={TYPES.map(t => ({ value: t, label: t.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()) }))}
                        />
                    </div>
                    <div>
                        <label className="label">Industry</label>
                        <input className="input" value={form.data.industry} onChange={e => form.setData('industry', e.target.value)} />
                    </div>
                </div>
                <div className="grid grid-cols-2 gap-3">
                    <div>
                        <label className="label">Phone</label>
                        <input className="input" value={form.data.phone} onChange={e => form.setData('phone', e.target.value)} />
                    </div>
                    <div>
                        <label className="label">Email</label>
                        <input type="email" className="input" value={form.data.email} onChange={e => form.setData('email', e.target.value)} />
                        {err('email') && <p className="mt-1 text-xs text-destructive">{err('email')}</p>}
                    </div>
                </div>
                <div className="grid grid-cols-2 gap-3">
                    <div>
                        <label className="label">Website</label>
                        <input className="input" value={form.data.website} onChange={e => form.setData('website', e.target.value)} placeholder="https://…" />
                        {err('website') && <p className="mt-1 text-xs text-destructive">{err('website')}</p>}
                    </div>
                    <div>
                        <label className="label">CAGE code</label>
                        <input className="input" value={form.data.cage_code} onChange={e => form.setData('cage_code', e.target.value)} />
                    </div>
                </div>
                <div className="grid grid-cols-2 gap-3">
                    <div>
                        <label className="label">City</label>
                        <input className="input" value={form.data.city} onChange={e => form.setData('city', e.target.value)} />
                    </div>
                    <div>
                        <label className="label">State</label>
                        <input className="input" value={form.data.state} onChange={e => form.setData('state', e.target.value)} />
                    </div>
                </div>
                <div>
                    <label className="label">Notes</label>
                    <textarea className="input min-h-[72px]" value={form.data.notes} onChange={e => form.setData('notes', e.target.value)} />
                </div>
            </form>
        </Modal>
    );
}
