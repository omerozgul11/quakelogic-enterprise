import { useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import { Modal } from '@/Components/ui/Modal';
import { Button } from '@/Components/ui/Button';
import { Select } from '@/Components/ui/Select';

interface Props {
    open: boolean;
    onClose: () => void;
    companies: { id: number; name: string }[];
    invoices: { id: number; number: string; company_id: number | null }[];
}

export function CreditNoteModal({ open, onClose, companies, invoices }: Props) {
    const form = useForm({
        company_id: '',
        crm_invoice_id: '',
        amount: '',
        currency: 'USD',
        reason: '',
        issued_at: new Date().toISOString().slice(0, 10),
        notes: '',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post('/finance/credit-notes', { preserveScroll: true, onSuccess: () => { form.reset(); onClose(); } });
    };
    const err = (k: keyof typeof form.data) => form.errors[k as keyof typeof form.errors];

    return (
        <Modal
            open={open}
            onClose={onClose}
            title="Issue Credit Note"
            footer={
                <>
                    <Button variant="ghost" onClick={onClose}>Cancel</Button>
                    <Button onClick={submit as unknown as () => void} disabled={form.processing}>{form.processing ? 'Issuing…' : 'Issue Credit Note'}</Button>
                </>
            }
        >
            <form onSubmit={submit} className="space-y-4">
                <div className="grid grid-cols-2 gap-3">
                    <div>
                        <label className="label">Client</label>
                        <Select className="w-full" value={form.data.company_id} placeholder="— None —" onChange={v => form.setData('company_id', v)} options={companies.map(c => ({ value: String(c.id), label: c.name }))} />
                    </div>
                    <div>
                        <label className="label">Against invoice</label>
                        <Select className="w-full" value={form.data.crm_invoice_id} placeholder="— None —" onChange={v => form.setData('crm_invoice_id', v)} options={invoices.map(i => ({ value: String(i.id), label: i.number }))} />
                    </div>
                </div>
                <div className="grid grid-cols-2 gap-3">
                    <div>
                        <label className="label">Amount *</label>
                        <input type="number" step="0.01" min="0.01" className="input" value={form.data.amount} onChange={e => form.setData('amount', e.target.value)} autoFocus />
                        {err('amount') && <p className="mt-1 text-xs text-destructive">{err('amount')}</p>}
                    </div>
                    <div><label className="label">Issued</label><input type="date" className="input" value={form.data.issued_at} onChange={e => form.setData('issued_at', e.target.value)} /></div>
                </div>
                <div>
                    <label className="label">Reason</label>
                    <input className="input" value={form.data.reason} onChange={e => form.setData('reason', e.target.value)} placeholder="Overcharge, returned goods…" />
                </div>
                <div>
                    <label className="label">Notes</label>
                    <textarea className="input min-h-[56px]" value={form.data.notes} onChange={e => form.setData('notes', e.target.value)} />
                </div>
            </form>
        </Modal>
    );
}
