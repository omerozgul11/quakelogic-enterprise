import { useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import { Modal } from '@/Components/ui/Modal';
import { Button } from '@/Components/ui/Button';
import { Select } from '@/Components/ui/Select';

interface Props {
    open: boolean;
    onClose: () => void;
    invoiceId: number;
    balance: number;
    currency: string;
}

const METHODS = [
    { value: 'Wire transfer', label: 'Wire transfer' },
    { value: 'Check', label: 'Check' },
    { value: 'ACH', label: 'ACH' },
    { value: 'Cash', label: 'Cash' },
    { value: 'Card (manual)', label: 'Card (manual)' },
];

export function RecordPaymentModal({ open, onClose, invoiceId, balance, currency }: Props) {
    const form = useForm({
        amount: String(balance > 0 ? balance : ''),
        method: 'Wire transfer',
        reference: '',
        paid_at: new Date().toISOString().slice(0, 10),
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post(`/finance/invoices/${invoiceId}/record-payment`, { preserveScroll: true, onSuccess: () => onClose() });
    };

    return (
        <Modal
            open={open}
            onClose={onClose}
            title="Record Payment"
            description="Log a payment received outside the gateway (wire, check, ACH…)."
            footer={
                <>
                    <Button variant="ghost" onClick={onClose}>Cancel</Button>
                    <Button variant="success" onClick={submit as unknown as () => void} disabled={form.processing}>{form.processing ? 'Saving…' : 'Record payment'}</Button>
                </>
            }
        >
            <form onSubmit={submit} className="space-y-3">
                <div className="grid grid-cols-2 gap-3">
                    <div>
                        <label className="label">Amount ({currency}) *</label>
                        <input type="number" step="0.01" min="0.01" className="input" value={form.data.amount} onChange={e => form.setData('amount', e.target.value)} autoFocus />
                        {form.errors.amount && <p className="mt-1 text-xs text-destructive">{form.errors.amount}</p>}
                    </div>
                    <div>
                        <label className="label">Method *</label>
                        <Select className="w-full" value={form.data.method} onChange={v => form.setData('method', v)} options={METHODS} />
                    </div>
                </div>
                <div className="grid grid-cols-2 gap-3">
                    <div><label className="label">Reference</label><input className="input" value={form.data.reference} onChange={e => form.setData('reference', e.target.value)} placeholder="Check #, wire ref…" /></div>
                    <div><label className="label">Date</label><input type="date" className="input" value={form.data.paid_at} onChange={e => form.setData('paid_at', e.target.value)} /></div>
                </div>
            </form>
        </Modal>
    );
}
