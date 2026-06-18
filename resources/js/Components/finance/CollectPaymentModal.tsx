import { useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import { Modal } from '@/Components/ui/Modal';
import { Button } from '@/Components/ui/Button';
import { formatCurrency } from '@/Lib/utils';

interface Props {
    open: boolean;
    onClose: () => void;
    invoiceId: number;
    balance: number;
    currency: string;
    provider: string;
}

export function CollectPaymentModal({ open, onClose, invoiceId, balance, currency, provider }: Props) {
    const form = useForm({ amount: String(balance > 0 ? balance : '') });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post(`/finance/invoices/${invoiceId}/collect`, { preserveScroll: true, onSuccess: () => onClose() });
    };

    return (
        <Modal
            open={open}
            onClose={onClose}
            title="Collect Payment Online"
            description={`Create a ${provider} payment link for this invoice.`}
            footer={
                <>
                    <Button variant="ghost" onClick={onClose}>Cancel</Button>
                    <Button onClick={submit as unknown as () => void} disabled={form.processing}>{form.processing ? 'Creating…' : 'Create payment link'}</Button>
                </>
            }
        >
            <form onSubmit={submit} className="space-y-3">
                <div>
                    <label className="label">Amount ({currency}) *</label>
                    <input type="number" step="0.01" min="0.01" max={balance} className="input w-48" value={form.data.amount} onChange={e => form.setData('amount', e.target.value)} autoFocus />
                    {form.errors.amount && <p className="mt-1 text-xs text-destructive">{form.errors.amount}</p>}
                    <p className="mt-1 text-xs text-muted-foreground">Balance due: {formatCurrency(balance, currency)}</p>
                </div>
            </form>
        </Modal>
    );
}
