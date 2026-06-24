import { useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import { Modal } from '@/Components/ui/Modal';
import { Button } from '@/Components/ui/Button';
import { Select } from '@/Components/ui/Select';
import { NumberInput } from '@/Components/ui/NumberInput';

export interface EditableLead {
    id: number;
    company?: string | null;        // company_name (free text)
    contact_name?: string | null;   // the lead / contact person
    phone?: string | null;
    product?: string | null;        // product_name
    owner_id?: number | null;
    email?: string | null;
    source?: string | null;
    status?: string;
    estimated_value?: number | null;
    probability?: number | null;
    expected_close_date?: string | null;
    notes?: string | null;
}

interface Props {
    open: boolean;
    onClose: () => void;
    lead?: EditableLead | null;
    owners: Array<{ id: number; name: string }>;
    currentUserId: number;
    sources: string[];
    statuses: Array<{ value: string; label: string }>;
}

export function LeadFormModal({ open, onClose, lead, owners, currentUserId, sources, statuses }: Props) {
    const isEdit = !!lead;
    const form = useForm({
        company_name: lead?.company ?? '',
        contact_name: lead?.contact_name ?? '',
        phone: lead?.phone ?? '',
        product_name: lead?.product ?? '',
        owner_id: String(lead?.owner_id ?? currentUserId),
        status: lead?.status ?? 'new',
        email: lead?.email ?? '',
        source: lead?.source ?? '',
        estimated_value: lead?.estimated_value != null ? String(lead.estimated_value) : '',
        probability: lead?.probability != null ? String(lead.probability) : '',
        expected_close_date: lead?.expected_close_date ?? '',
        notes: lead?.notes ?? '',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.transform(data => ({
            ...data,
            owner_id: data.owner_id || null,
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
                <div className="grid grid-cols-2 gap-3">
                    <div>
                        <label className="label">Company name *</label>
                        <input className="input" value={form.data.company_name} onChange={e => form.setData('company_name', e.target.value)} autoFocus placeholder="e.g. City of Reno" />
                        {err('company_name') && <p className="mt-1 text-xs text-destructive">{err('company_name')}</p>}
                    </div>
                    <div>
                        <label className="label">Lead name (person) *</label>
                        <input className="input" value={form.data.contact_name} onChange={e => form.setData('contact_name', e.target.value)} placeholder="e.g. Jane Doe" />
                        {err('contact_name') && <p className="mt-1 text-xs text-destructive">{err('contact_name')}</p>}
                    </div>
                </div>
                <div className="grid grid-cols-2 gap-3">
                    <div>
                        <label className="label">Phone *</label>
                        <input className="input" value={form.data.phone} onChange={e => form.setData('phone', e.target.value)} placeholder="(555) 123-4567" />
                        {err('phone') && <p className="mt-1 text-xs text-destructive">{err('phone')}</p>}
                    </div>
                    <div>
                        <label className="label">Product *</label>
                        <input className="input" value={form.data.product_name} onChange={e => form.setData('product_name', e.target.value)} placeholder="QuakeLogic product" />
                        {err('product_name') && <p className="mt-1 text-xs text-destructive">{err('product_name')}</p>}
                    </div>
                </div>
                <div className="grid grid-cols-2 gap-3">
                    <div>
                        <label className="label">Owner (pursuing)</label>
                        <Select className="w-full" value={form.data.owner_id} onChange={v => form.setData('owner_id', v)}
                            options={owners.map(o => ({ value: String(o.id), label: o.name }))} />
                    </div>
                    <div>
                        <label className="label">Stage</label>
                        <Select className="w-full" value={form.data.status} onChange={v => form.setData('status', v)}
                            options={statuses.map(s => ({ value: s.value, label: s.label }))} />
                    </div>
                </div>
                <div className="grid grid-cols-2 gap-3">
                    <div>
                        <label className="label">Email</label>
                        <input type="email" className="input" value={form.data.email} onChange={e => form.setData('email', e.target.value)} />
                        {err('email') && <p className="mt-1 text-xs text-destructive">{err('email')}</p>}
                    </div>
                    <div>
                        <label className="label">Source</label>
                        <Select className="w-full" value={form.data.source} onChange={v => form.setData('source', v)} placeholder="— None —"
                            options={sources.map(s => ({ value: s, label: s }))} />
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
                    <textarea className="input min-h-[64px]" value={form.data.notes} onChange={e => form.setData('notes', e.target.value)} />
                </div>
            </form>
        </Modal>
    );
}
