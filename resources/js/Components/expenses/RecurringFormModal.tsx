import { useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import { Modal } from '@/Components/ui/Modal';
import { Button } from '@/Components/ui/Button';
import { Select } from '@/Components/ui/Select';
import { Checkbox } from '@/Components/ui/Checkbox';

interface Option { value: number | string; label: string }

export interface RecurringFormOptions {
    categories: Option[];
    frequencies: { value: string; label: string }[];
    paymentMethods: { value: string; label: string }[];
}

export interface EditableRecurring {
    id: number;
    name?: string;
    vendor?: string | null;
    amount?: number | string;
    currency?: string;
    payment_method?: string | null;
    frequency?: string;
    interval_count?: number;
    start_date?: string | null;
    end_date?: string | null;
    auto_approve?: boolean;
    is_active?: boolean;
    is_billable?: boolean;
    category_id?: number | null;
}

interface Props {
    open: boolean;
    onClose: () => void;
    recurring?: EditableRecurring | null;
    formOptions: RecurringFormOptions;
}

const strOpts = (arr: Option[]) => arr.map(o => ({ value: String(o.value), label: o.label }));
const idStr = (v?: number | null) => (v != null ? String(v) : '');

export function RecurringFormModal({ open, onClose, recurring, formOptions }: Props) {
    const isEdit = !!recurring;
    const today = new Date().toISOString().slice(0, 10);

    const form = useForm({
        name: recurring?.name ?? '',
        vendor: recurring?.vendor ?? '',
        amount: recurring?.amount != null ? String(recurring.amount) : '',
        currency: recurring?.currency ?? 'USD',
        payment_method: recurring?.payment_method ?? '',
        frequency: recurring?.frequency ?? 'monthly',
        interval_count: recurring?.interval_count != null ? String(recurring.interval_count) : '1',
        start_date: recurring?.start_date ?? today,
        end_date: recurring?.end_date ?? '',
        auto_approve: recurring?.auto_approve ?? false,
        is_active: recurring?.is_active ?? true,
        is_billable: recurring?.is_billable ?? false,
        expense_category_id: idStr(recurring?.category_id),
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.transform((data) => ({
            ...data,
            amount: data.amount === '' ? null : Number(data.amount),
            interval_count: data.interval_count ? Number(data.interval_count) : 1,
            payment_method: data.payment_method || null,
            end_date: data.end_date || null,
            expense_category_id: data.expense_category_id ? Number(data.expense_category_id) : null,
        }));
        const opts = { preserveScroll: true, onSuccess: () => { if (!isEdit) form.reset(); onClose(); } };
        if (isEdit) form.put(`/expenses/recurring/${recurring!.id}`, opts);
        else form.post('/expenses/recurring', opts);
    };

    const err = (k: keyof typeof form.data) => form.errors[k as keyof typeof form.errors];

    // "Every N" is the repeat multiplier: e.g. Monthly + 2 = every 2 months.
    // Show the period unit next to the number so the cadence is unambiguous.
    const FREQ_UNIT: Record<string, string> = { daily: 'day', weekly: 'week', monthly: 'month', quarterly: 'quarter', yearly: 'year' };
    const intervalN = Number(form.data.interval_count) || 1;
    const intervalUnit = `${FREQ_UNIT[form.data.frequency] ?? 'period'}${intervalN === 1 ? '' : 's'}`;

    return (
        <Modal
            open={open}
            onClose={onClose}
            size="lg"
            title={isEdit ? 'Edit Recurring Cost' : 'Add Recurring Cost'}
            description="Register a fixed cost once — an expense is created automatically each period."
            footer={
                <>
                    <Button variant="ghost" onClick={onClose}>Cancel</Button>
                    <Button onClick={submit as unknown as () => void} disabled={form.processing}>
                        {form.processing ? 'Saving…' : isEdit ? 'Save Changes' : 'Add Recurring Cost'}
                    </Button>
                </>
            }
        >
            <form onSubmit={submit} className="space-y-4">
                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                        <label className="label">Name *</label>
                        <input className="input" value={form.data.name} onChange={e => form.setData('name', e.target.value)} autoFocus placeholder="e.g. AWS subscription" />
                        {err('name') && <p className="mt-1 text-xs text-destructive">{err('name')}</p>}
                    </div>
                    <div>
                        <label className="label">Vendor *</label>
                        <input className="input" value={form.data.vendor} onChange={e => form.setData('vendor', e.target.value)} />
                        {err('vendor') && <p className="mt-1 text-xs text-destructive">{err('vendor')}</p>}
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-3 sm:grid-cols-4">
                    <div>
                        <label className="label">Amount *</label>
                        <input type="number" step="0.01" min="0" className="input" value={form.data.amount} onChange={e => form.setData('amount', e.target.value)} />
                        {err('amount') && <p className="mt-1 text-xs text-destructive">{err('amount')}</p>}
                    </div>
                    <div>
                        <label className="label">Currency</label>
                        <input className="input uppercase" maxLength={3} value={form.data.currency} onChange={e => form.setData('currency', e.target.value.toUpperCase())} />
                    </div>
                    <div>
                        <label className="label">Frequency *</label>
                        <Select className="w-full" value={form.data.frequency} onChange={v => form.setData('frequency', v)} options={formOptions.frequencies} />
                    </div>
                    <div>
                        <label className="label">Repeat every</label>
                        <div className="flex items-center gap-2">
                            <input type="number" min="1" className="input w-20" value={form.data.interval_count} onChange={e => form.setData('interval_count', e.target.value)} />
                            <span className="text-sm text-muted-foreground">{intervalUnit}</span>
                        </div>
                        {err('interval_count') && <p className="mt-1 text-xs text-destructive">{err('interval_count')}</p>}
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <div>
                        <label className="label">Start date *</label>
                        <input type="date" className="input" value={form.data.start_date} onChange={e => form.setData('start_date', e.target.value)} />
                        {err('start_date') && <p className="mt-1 text-xs text-destructive">{err('start_date')}</p>}
                    </div>
                    <div>
                        <label className="label">End date</label>
                        <input type="date" className="input" value={form.data.end_date} onChange={e => form.setData('end_date', e.target.value)} placeholder="Open-ended" />
                        {err('end_date') && <p className="mt-1 text-xs text-destructive">{err('end_date')}</p>}
                    </div>
                    <div>
                        <label className="label">Category</label>
                        <Select className="w-full" value={form.data.expense_category_id} onChange={v => form.setData('expense_category_id', v)} options={strOpts(formOptions.categories)} placeholder="Uncategorized" />
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                        <label className="label">Payment method</label>
                        <Select className="w-full" value={form.data.payment_method} onChange={v => form.setData('payment_method', v)} options={formOptions.paymentMethods} placeholder="Not specified" />
                    </div>
                    <div className="flex flex-col justify-end gap-2 pb-1">
                        <div className="flex items-center gap-2 text-sm text-foreground">
                            <Checkbox checked={form.data.auto_approve} onChange={v => form.setData('auto_approve', v)} ariaLabel="Auto-approve generated expenses" />
                            <span className="cursor-pointer" onClick={() => form.setData('auto_approve', !form.data.auto_approve)}>Auto-approve generated expenses</span>
                        </div>
                        <div className="flex items-center gap-2 text-sm text-foreground">
                            <Checkbox checked={form.data.is_billable} onChange={v => form.setData('is_billable', v)} ariaLabel="Billable" />
                            <span className="cursor-pointer" onClick={() => form.setData('is_billable', !form.data.is_billable)}>Billable</span>
                        </div>
                        <div className="flex items-center gap-2 text-sm text-foreground">
                            <Checkbox checked={form.data.is_active} onChange={v => form.setData('is_active', v)} ariaLabel="Active" />
                            <span className="cursor-pointer" onClick={() => form.setData('is_active', !form.data.is_active)}>Active</span>
                        </div>
                    </div>
                </div>
            </form>
        </Modal>
    );
}
