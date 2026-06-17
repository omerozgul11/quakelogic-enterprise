import { useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import { Modal } from '@/Components/ui/Modal';
import { Button } from '@/Components/ui/Button';
import { Select } from '@/Components/ui/Select';
import { NumberInput } from '@/Components/ui/NumberInput';

export interface EditableLead {
    id: number;
    title?: string;
    contact_name?: string | null;
    email?: string | null;
    phone?: string | null;
    source?: string | null;
    status?: string;
    estimated_value?: number | null;
    probability?: number | null;
    expected_close_date?: string | null;
    company_id?: number | null;
    notes?: string | null;
}

interface Props {
    open: boolean;
    onClose: () => void;
    lead?: EditableLead | null;
    companies: Array<{ id: number; name: string }>;
    sources: string[];
    statuses: Array<{ value: string; label: string }>;
}

export function LeadFormModal({ open, onClose, lead, companies, sources, statuses }: Props) {
    const isEdit = !!lead;
    const form = useForm({
        title: lead?.title ?? '',
        contact_name: lead?.contact_name ?? '',
        email: lead?.email ?? '',
        phone: lead?.phone ?? '',
        source: lead?.source ?? '',
        status: lead?.status ?? 'new',
        estimated_value: lead?.estimated_value != null ? String(lead.estimated_value) : '',
        probability: lead?.probability != null ? String(lead.probability) : '',
        expected_close_date: lead?.expected_close_date ?? '',
        company_id: lead?.company_id ? String(lead.company_id) : '',
        notes: lead?.notes ?? '',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.transform(data => ({
            ...data,
            company_id: data.company_id || null,
            estimated_value: data.estimated_value || null,
            probability: data.probability || null,
            expected_close_date: data.expected_close_date || null,
            source: data.source || null,
        }));
        const opts = { preserveScroll: true, onSuccess: () => { form.reset(); onClose(); } };
        if (isEdit) form.put(`/crm/leads/${lead!.id}`, opts);
        else form.post('/crm/leads', opts);
    };

    const err = (k: keyof typeof form.data) => form.errors[k as keyof typeof form.errors];

    return (
        <Modal
            open={open}
            onClose={onClose}
            title={isEdit ? 'Edit Lead' : 'Add Lead'}
            description={isEdit ? 'Update this lead.' : 'Add a lead to your pipeline.'}
            footer={
                <>
                    <Button variant="ghost" onClick={onClose}>Cancel</Button>
                    <Button onClick={submit as unknown as () => void} disabled={form.processing}>
                        {form.processing ? 'Saving…' : isEdit ? 'Save Changes' : 'Add Lead'}
                    </Button>
                </>
            }
        >
            <form onSubmit={submit} className="space-y-4">
                <div>
                    <label className="label">Title / opportunity *</label>
                    <input className="input" value={form.data.title} onChange={e => form.setData('title', e.target.value)} autoFocus placeholder="e.g. City of Reno — SCADA upgrade" />
                    {err('title') && <p className="mt-1 text-xs text-destructive">{err('title')}</p>}
                </div>
                <div className="grid grid-cols-2 gap-3">
                    <div>
                        <label className="label">Company</label>
                        <Select className="w-full" value={form.data.company_id} onChange={v => form.setData('company_id', v)} placeholder="— None —"
                            options={companies.map(c => ({ value: String(c.id), label: c.name }))} />
                    </div>
                    <div>
                        <label className="label">Stage</label>
                        <Select className="w-full" value={form.data.status} onChange={v => form.setData('status', v)}
                            options={statuses.map(s => ({ value: s.value, label: s.label }))} />
                    </div>
                </div>
                <div className="grid grid-cols-2 gap-3">
                    <div>
                        <label className="label">Contact name</label>
                        <input className="input" value={form.data.contact_name} onChange={e => form.setData('contact_name', e.target.value)} />
                    </div>
                    <div>
                        <label className="label">Source</label>
                        <Select className="w-full" value={form.data.source} onChange={v => form.setData('source', v)} placeholder="— None —"
                            options={sources.map(s => ({ value: s, label: s }))} />
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
                <div className="grid grid-cols-3 gap-3">
                    <div>
                        <label className="label">Est. value</label>
                        <NumberInput className="input" value={form.data.estimated_value} onChange={e => form.setData('estimated_value', e.target.value)} placeholder="0.00" />
                    </div>
                    <div>
                        <label className="label">Win %</label>
                        <NumberInput allowDecimal={false} className="input" value={form.data.probability} onChange={e => form.setData('probability', e.target.value)} placeholder="0" />
                        {err('probability') && <p className="mt-1 text-xs text-destructive">{err('probability')}</p>}
                    </div>
                    <div>
                        <label className="label">Close date</label>
                        <input type="date" className="input" value={form.data.expected_close_date} onChange={e => form.setData('expected_close_date', e.target.value)} />
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
