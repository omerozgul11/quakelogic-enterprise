import { useForm } from '@inertiajs/react';
import axios from 'axios';
import { DragEvent, FormEvent, useRef, useState } from 'react';
import { UploadCloud, X, Loader2, Sparkles, FileText } from 'lucide-react';
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
    due_date?: string | null;
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

/** Fields the receipt reader may return to pre-fill the form. */
interface ExtractedFields {
    vendor?: string;
    amount?: number;
    currency?: string;
    expense_date?: string;
    due_date?: string;
    description?: string;
    notes?: string;
    payment_method?: string;
    expense_category_id?: number;
}

export function ExpenseFormModal({ open, onClose, expense, formOptions }: Props) {
    const isEdit = !!expense;
    const today = new Date().toISOString().slice(0, 10);

    const form = useForm<{
        vendor: string; description: string; amount: string; currency: string;
        payment_method: string; expense_date: string; due_date: string; is_billable: boolean;
        expense_category_id: string; company_id: string; crm_project_id: string; proposal_id: string;
        notes: string; receipt: File | null;
    }>({
        vendor: expense?.vendor ?? '',
        description: expense?.description ?? '',
        amount: expense?.amount != null ? String(expense.amount) : '',
        currency: expense?.currency ?? 'USD',
        payment_method: expense?.payment_method ?? '',
        expense_date: expense?.expense_date ?? today,
        due_date: expense?.due_date ?? '',
        is_billable: expense?.is_billable ?? false,
        expense_category_id: idStr(expense?.category_id),
        company_id: idStr(expense?.company_id),
        crm_project_id: idStr(expense?.crm_project_id),
        proposal_id: idStr(expense?.proposal_id),
        notes: expense?.notes ?? '',
        receipt: null,
    });

    const fileRef = useRef<HTMLInputElement>(null);
    const [reading, setReading] = useState(false);
    const [dragOver, setDragOver] = useState(false);
    const [readMsg, setReadMsg] = useState<string | null>(null);

    // Drop a receipt/invoice → attach it and let the AI pre-fill the form.
    const handleFile = async (file: File | null) => {
        if (!file) return;
        form.setData('receipt', file);
        setReadMsg(null);
        setReading(true);
        try {
            const fd = new FormData();
            fd.append('file', file);
            const { data } = await axios.post<{ status: string; fields: ExtractedFields }>('/expenses/extract', fd);
            const f = data.fields ?? {};
            const applied: string[] = [];
            const updates: Record<string, unknown> = {};
            if (f.vendor) { updates.vendor = f.vendor; applied.push('vendor'); }
            if (f.amount != null) { updates.amount = String(f.amount); applied.push('amount'); }
            if (f.currency) updates.currency = String(f.currency).toUpperCase();
            if (f.expense_date) { updates.expense_date = f.expense_date; applied.push('date'); }
            if (f.due_date) { updates.due_date = f.due_date; applied.push('due date'); }
            if (f.description) { updates.description = f.description; applied.push('description'); }
            if (f.notes) updates.notes = f.notes;
            if (f.payment_method) updates.payment_method = f.payment_method;
            if (f.expense_category_id) { updates.expense_category_id = String(f.expense_category_id); applied.push('category'); }

            form.setData(d => ({ ...d, ...updates, receipt: file }));

            if (data.status === 'ok' && applied.length) {
                setReadMsg(`Read ${applied.join(', ')} from the file — please review before saving.`);
            } else if (data.status === 'unavailable') {
                setReadMsg('Automatic reading isn’t enabled — the file is attached; enter the details below.');
            } else {
                setReadMsg('Couldn’t read the details automatically — the file is attached; enter them below.');
            }
        } catch {
            setReadMsg('Couldn’t read the file — it’s still attached; enter the details below.');
        } finally {
            setReading(false);
        }
    };

    const onDrop = (e: DragEvent<HTMLButtonElement>) => {
        e.preventDefault();
        setDragOver(false);
        handleFile(e.dataTransfer.files?.[0] ?? null);
    };

    const clearReceipt = () => {
        form.setData('receipt', null);
        setReadMsg(null);
        if (fileRef.current) fileRef.current.value = '';
    };

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.transform((data) => ({
            ...data,
            amount: data.amount === '' ? null : Number(data.amount),
            due_date: data.due_date || null,
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
    const receipt = form.data.receipt;

    return (
        <Modal
            open={open}
            onClose={onClose}
            size="lg"
            title={isEdit ? 'Edit Expense' : 'Add Expense'}
            footer={
                <>
                    <Button variant="ghost" onClick={onClose}>Cancel</Button>
                    <Button onClick={submit as unknown as () => void} disabled={form.processing || reading}>
                        {form.processing ? 'Saving…' : isEdit ? 'Save Changes' : 'Add Expense'}
                    </Button>
                </>
            }
        >
            <form onSubmit={submit} className="space-y-4">
                {!isEdit && (
                    <div>
                        <input
                            ref={fileRef}
                            type="file"
                            accept="application/pdf,image/jpeg,image/png,image/heic,image/heif"
                            className="hidden"
                            onChange={e => handleFile(e.target.files?.[0] ?? null)}
                        />
                        {!receipt ? (
                            <button
                                type="button"
                                onClick={() => fileRef.current?.click()}
                                onDragOver={e => { e.preventDefault(); setDragOver(true); }}
                                onDragLeave={() => setDragOver(false)}
                                onDrop={onDrop}
                                className={`flex w-full flex-col items-center gap-1.5 rounded-xl border-2 border-dashed px-4 py-6 text-center transition-colors ${dragOver ? 'border-primary bg-primary/5' : 'border-border hover:border-primary/50 hover:bg-secondary/40'}`}
                            >
                                <UploadCloud className="h-6 w-6 text-muted-foreground" />
                                <span className="text-sm font-medium text-foreground">Drop a receipt or invoice — we’ll read it and fill this in</span>
                                <span className="inline-flex items-center gap-1 text-xs text-muted-foreground"><Sparkles className="h-3 w-3" /> PDF or photo · you review before saving · the file is kept as the receipt</span>
                            </button>
                        ) : (
                            <div className="flex items-center gap-3 rounded-xl border border-border bg-secondary/40 px-3 py-2.5">
                                {reading ? <Loader2 className="h-5 w-5 shrink-0 animate-spin text-primary" /> : <FileText className="h-5 w-5 shrink-0 text-muted-foreground" />}
                                <div className="min-w-0 flex-1">
                                    <p className="truncate text-sm font-medium text-foreground">{receipt.name}</p>
                                    <p className="truncate text-xs text-muted-foreground">{reading ? 'Reading the document…' : (readMsg ?? 'Attached as the receipt.')}</p>
                                </div>
                                <button type="button" onClick={clearReceipt} className="shrink-0 rounded p-1 text-muted-foreground hover:bg-secondary hover:text-foreground" aria-label="Remove file">
                                    <X className="h-4 w-4" />
                                </button>
                            </div>
                        )}
                        {readMsg && !receipt && <p className="mt-1 text-xs text-muted-foreground">{readMsg}</p>}
                    </div>
                )}

                <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
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

                <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <div>
                        <label className="label">Payment method</label>
                        <Select className="w-full" value={form.data.payment_method} onChange={v => form.setData('payment_method', v)} options={formOptions.paymentMethods} placeholder="Not specified" />
                    </div>
                    <div>
                        <label className="label">Due date</label>
                        <input type="date" className="input" value={form.data.due_date} onChange={e => form.setData('due_date', e.target.value)} />
                    </div>
                    <div className="flex items-end">
                        <div className="flex items-center gap-2 pb-2 text-sm text-foreground">
                            <Checkbox checked={form.data.is_billable} onChange={v => form.setData('is_billable', v)} ariaLabel="Billable" />
                            <span className="cursor-pointer" onClick={() => form.setData('is_billable', !form.data.is_billable)}>Billable</span>
                        </div>
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <div>
                        <label className="label">Client / Sale</label>
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
