import { useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import { Modal } from '@/Components/ui/Modal';
import { Button } from '@/Components/ui/Button';
import { Select } from '@/Components/ui/Select';
import { Checkbox } from '@/Components/ui/Checkbox';

interface Option { value: number | string; label: string }

export interface ExpenseFormOptions {
    categories: Option[];
    companies: Option[];
    projects: Option[];
    proposals: Option[];
    paymentMethods: { value: string; label: string }[];
}

export interface EditableExpense {
    id: number;
    vendor?: string | null;
    description?: string | null;
    amount?: number | string;
    currency?: string;
    payment_method?: string | null;
    expense_date?: string | null;
    is_billable?: boolean;
    category_id?: number | null;
    company_id?: number | null;
    crm_project_id?: number | null;
    proposal_id?: number | null;
    notes?: string | null;
}

interface Props {
    open: boolean;
    onClose: () => void;
    expense?: EditableExpense | null;
    formOptions: ExpenseFormOptions;
}

const strOpts = (arr: Option[]) => arr.map(o => ({ value: String(o.value), label: o.label }));
const idStr = (v?: number | null) => (v != null ? String(v) : '');

export function ExpenseFormModal({ open, onClose, expense, formOptions }: Props) {
    const isEdit = !!expense;
    const today = new Date().toISOString().slice(0, 10);

    const form = useForm({
        vendor: expense?.vendor ?? '',
        description: expense?.description ?? '',
        amount: expense?.amount != null ? String(expense.amount) : '',
        currency: expense?.currency ?? 'USD',
        payment_method: expense?.payment_method ?? '',
        expense_date: expense?.expense_date ?? today,
        is_billable: expense?.is_billable ?? false,
        expense_category_id: idStr(expense?.category_id),
        company_id: idStr(expense?.company_id),
        crm_project_id: idStr(expense?.crm_project_id),
        proposal_id: idStr(expense?.proposal_id),
        notes: expense?.notes ?? '',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.transform((data) => ({
            ...data,
            amount: data.amount === '' ? null : Number(data.amount),
            payment_method: data.payment_method || null,
            expense_category_id: data.expense_category_id ? Number(data.expense_category_id) : null,
            company_id: data.company_id ? Number(data.company_id) : null,
            crm_project_id: data.crm_project_id ? Number(data.crm_project_id) : null,
            proposal_id: data.proposal_id ? Number(data.proposal_id) : null,
        }));
        const opts = { preserveScroll: true, onSuccess: () => { if (!isEdit) form.reset(); onClose(); } };
        if (isEdit) form.put(`/expenses/list/${expense!.id}`, opts);
        else form.post('/expenses/list', opts);
    };

    const err = (k: keyof typeof form.data) => form.errors[k as keyof typeof form.errors];

    return (
        <Modal
            open={open}
            onClose={onClose}
            size="lg"
            title={isEdit ? 'Edit Expense' : 'Add Expense'}
            footer={
                <>
                    <Button variant="ghost" onClick={onClose}>Cancel</Button>
                    <Button onClick={submit as unknown as () => void} disabled={form.processing}>
                        {form.processing ? 'Saving…' : isEdit ? 'Save Changes' : 'Add Expense'}
                    </Button>
                </>
            }
        >
            <form onSubmit={submit} className="space-y-4">
                <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <div>
                        <label className="label">Amount *</label>
                        <input type="number" step="0.01" min="0" className="input" value={form.data.amount} onChange={e => form.setData('amount', e.target.value)} autoFocus />
                        {err('amount') && <p className="mt-1 text-xs text-destructive">{err('amount')}</p>}
                    </div>
                    <div>
                        <label className="label">Currency</label>
                        <input className="input uppercase" maxLength={3} value={form.data.currency} onChange={e => form.setData('currency', e.target.value.toUpperCase())} />
                    </div>
                    <div>
                        <label className="label">Date *</label>
                        <input type="date" className="input" value={form.data.expense_date} onChange={e => form.setData('expense_date', e.target.value)} />
                        {err('expense_date') && <p className="mt-1 text-xs text-destructive">{err('expense_date')}</p>}
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                        <label className="label">Vendor / Merchant *</label>
                        <input className="input" value={form.data.vendor} onChange={e => form.setData('vendor', e.target.value)} placeholder="e.g. Amazon Web Services" />
                        {err('vendor') && <p className="mt-1 text-xs text-destructive">{err('vendor')}</p>}
                    </div>
                    <div>
                        <label className="label">Category</label>
                        <Select className="w-full" value={form.data.expense_category_id} onChange={v => form.setData('expense_category_id', v)} options={strOpts(formOptions.categories)} placeholder="Uncategorized" />
                    </div>
                </div>

                <div>
                    <label className="label">Description *</label>
                    <input className="input" value={form.data.description} onChange={e => form.setData('description', e.target.value)} placeholder="What was this expense for?" />
                    {err('description') && <p className="mt-1 text-xs text-destructive">{err('description')}</p>}
                </div>

                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                        <label className="label">Payment method</label>
                        <Select className="w-full" value={form.data.payment_method} onChange={v => form.setData('payment_method', v)} options={formOptions.paymentMethods} placeholder="Not specified" />
                    </div>
                    <div className="flex items-end">
                        <div className="flex items-center gap-2 pb-2 text-sm text-foreground">
                            <Checkbox checked={form.data.is_billable} onChange={v => form.setData('is_billable', v)} ariaLabel="Billable" />
                            <span className="cursor-pointer" onClick={() => form.setData('is_billable', !form.data.is_billable)}>Billable to a client / project</span>
                        </div>
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <div>
                        <label className="label">Client</label>
                        <Select className="w-full" value={form.data.company_id} onChange={v => form.setData('company_id', v)} options={strOpts(formOptions.companies)} placeholder="None" />
                    </div>
                    <div>
                        <label className="label">Project</label>
                        <Select className="w-full" value={form.data.crm_project_id} onChange={v => form.setData('crm_project_id', v)} options={strOpts(formOptions.projects)} placeholder="None" />
                    </div>
                    <div>
                        <label className="label">Proposal</label>
                        <Select className="w-full" value={form.data.proposal_id} onChange={v => form.setData('proposal_id', v)} options={strOpts(formOptions.proposals)} placeholder="None" />
                    </div>
                </div>

                <div>
                    <label className="label">Notes</label>
                    <textarea className="input min-h-[56px]" value={form.data.notes} onChange={e => form.setData('notes', e.target.value)} />
                </div>
            </form>
        </Modal>
    );
}
