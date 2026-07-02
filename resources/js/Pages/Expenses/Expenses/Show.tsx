import { Head, Link, router, useForm } from '@inertiajs/react';
import { FormEvent, useRef, useState } from 'react';
import { ExpenseLayout } from '@/Components/layout/ExpenseLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { Pill } from '@/Components/ui/Pill';
import { Modal } from '@/Components/ui/Modal';
import { Select } from '@/Components/ui/Select';
import { ExpenseFormModal, ExpenseFormOptions, EditableExpense } from '@/Components/expenses/ExpenseFormModal';
import { formatCurrency, formatDate, formatDateTime } from '@/Lib/utils';
import { Receipt, Pencil, Trash2, Send, Check, X, BadgeDollarSign, Upload, Download, Paperclip, Building2, FolderKanban, FileText, Plus } from 'lucide-react';

interface Attachment { id: number; display_name: string; size: number | null; mime_type: string | null; uploaded_by: string | null; created_at: string | null }

interface Payment { id: number; amount: number; currency: string; paid_on: string | null; method: string | null; method_label: string | null; reference: string | null; note: string | null; created_by: string | null }

interface ExpenseDetail {
    id: number; number: string; vendor: string | null; description: string | null;
    amount: number; currency: string; payment_method: string | null; payment_method_label: string | null;
    amount_paid: number; balance_due: number; payment_status: string; payment_status_label: string;
    payment_status_color: string; due_date: string | null; is_overdue: boolean; paid_at: string | null;
    status: string; status_label: string; status_color: string; source: string; is_billable: boolean;
    expense_date: string | null; category: string | null; category_id: number | null;
    company_id: number | null; crm_project_id: number | null; proposal_id: number | null;
    owner: string | null; notes: string | null; reject_reason: string | null;
    submitted_at: string | null; approved_at: string | null; reimbursed_at: string | null;
    approver: string | null; company: string | null; project: string | null; proposal: string | null;
    attachments: Attachment[]; payments: Payment[];
}

interface Props {
    expense: ExpenseDetail;
    formOptions: ExpenseFormOptions;
    statuses: { value: string; label: string }[];
    paymentMethods: { value: string; label: string }[];
    can: { manage: boolean; update: boolean; submit: boolean; approve: boolean; reimburse: boolean; delete: boolean; recordPayment: boolean };
}

function fileSize(bytes: number | null): string {
    if (!bytes) return '';
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1048576) return `${(bytes / 1024).toFixed(0)} KB`;
    return `${(bytes / 1048576).toFixed(1)} MB`;
}

export default function ExpenseShow({ expense, formOptions, paymentMethods, can }: Props) {
    const [editOpen, setEditOpen] = useState(false);
    const [rejectOpen, setRejectOpen] = useState(false);
    const [payOpen, setPayOpen] = useState(false);
    const fileRef = useRef<HTMLInputElement>(null);
    const today = new Date().toISOString().slice(0, 10);

    const post = (action: string) => router.post(`/expenses/list/${expense.id}/${action}`, {}, { preserveScroll: true });
    const reject = useForm({ reason: '' });
    const upload = useForm<{ file: File | null }>({ file: null });
    const pay = useForm({ amount: expense.balance_due > 0 ? String(expense.balance_due) : '', paid_on: today, method: '', reference: '', note: '' });

    const paidPct = expense.amount > 0 ? Math.min(100, Math.round((expense.amount_paid / expense.amount) * 100)) : 0;

    const submitPay = (e: FormEvent) => {
        e.preventDefault();
        pay.transform((d) => ({ ...d, amount: d.amount === '' ? null : Number(d.amount), method: d.method || null }));
        pay.post(`/expenses/list/${expense.id}/payments`, { preserveScroll: true, onSuccess: () => { pay.reset(); pay.setData('paid_on', today); setPayOpen(false); } });
    };

    const removePayment = (id: number) => {
        if (confirm('Remove this payment?')) router.delete(`/expenses/list/${expense.id}/payments/${id}`, { preserveScroll: true });
    };

    const submitReject = (e: FormEvent) => {
        e.preventDefault();
        reject.post(`/expenses/list/${expense.id}/reject`, { preserveScroll: true, onSuccess: () => { reject.reset(); setRejectOpen(false); } });
    };

    const onFile = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (!file) return;
        upload.transform(() => ({ file }));
        upload.post(`/expenses/list/${expense.id}/receipts`, {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => { upload.reset(); if (fileRef.current) fileRef.current.value = ''; },
        });
    };

    const removeReceipt = (id: number) => {
        if (confirm('Remove this receipt?')) router.delete(`/expenses/list/${expense.id}/receipts/${id}`, { preserveScroll: true });
    };

    const destroy = () => {
        if (confirm(`Delete expense ${expense.number}? This cannot be undone.`)) router.delete(`/expenses/list/${expense.id}`);
    };

    const editable: EditableExpense = {
        id: expense.id, vendor: expense.vendor, description: expense.description, amount: expense.amount,
        currency: expense.currency, payment_method: expense.payment_method, expense_date: expense.expense_date,
        due_date: expense.due_date, is_billable: expense.is_billable, category_id: expense.category_id,
        company_id: expense.company_id, crm_project_id: expense.crm_project_id, proposal_id: expense.proposal_id,
        notes: expense.notes,
    };

    return (
        <ExpenseLayout>
            <Head title={`${expense.number} · Expenses`} />
            <div className="p-4 sm:p-6">
                <Link href="/expenses/list" className="mb-3 inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground">← Back to expenses</Link>

                <PageHeader
                    icon={Receipt}
                    title={expense.vendor ?? expense.description ?? expense.number}
                    description={expense.number}
                    actions={
                        <div className="flex flex-wrap items-center gap-2">
                            {can.submit && (expense.status === 'draft' || expense.status === 'rejected') && (
                                <Button variant="secondary" icon={Send} onClick={() => post('submit')}>Submit</Button>
                            )}
                            {can.approve && expense.status === 'submitted' && (
                                <>
                                    <Button icon={Check} onClick={() => post('approve')}>Approve</Button>
                                    <Button variant="ghost" icon={X} onClick={() => setRejectOpen(true)}>Reject</Button>
                                </>
                            )}
                            {can.reimburse && expense.status === 'approved' && (
                                <Button icon={BadgeDollarSign} onClick={() => post('reimburse')}>Mark Reimbursed</Button>
                            )}
                            {can.update && <Button variant="secondary" icon={Pencil} onClick={() => setEditOpen(true)}>Edit</Button>}
                            {can.delete && <Button variant="ghost" icon={Trash2} onClick={destroy}>Delete</Button>}
                        </div>
                    }
                />

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <div className="space-y-6 lg:col-span-2">
                        <Card className="p-5">
                            <div className="flex items-start justify-between">
                                <div>
                                    <p className="text-3xl font-bold tracking-tight text-foreground">{formatCurrency(expense.amount, expense.currency)}</p>
                                    <p className="mt-1 text-sm text-muted-foreground">{expense.category ?? 'Uncategorized'} · {formatDate(expense.expense_date)}</p>
                                </div>
                                <div className="flex flex-col items-end gap-1.5">
                                    <Pill color={expense.status_color} label={expense.status_label} />
                                    {expense.source === 'quickbooks' && <Pill color="blue" label="From QuickBooks" />}
                                </div>
                            </div>

                            {expense.reject_reason && (
                                <div className="mt-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300">
                                    <span className="font-semibold">Rejected:</span> {expense.reject_reason}
                                </div>
                            )}

                            <dl className="mt-5 grid grid-cols-2 gap-4 text-sm sm:grid-cols-3">
                                <Detail label="Owner" value={expense.owner} />
                                <Detail label="Payment method" value={expense.payment_method_label} />
                                <Detail label="Billable" value={expense.is_billable ? 'Yes' : 'No'} />
                                {expense.approver && <Detail label="Approved by" value={expense.approver} />}
                                {expense.description && <Detail label="Description" value={expense.description} />}
                            </dl>

                            {(expense.company || expense.project || expense.proposal) && (
                                <div className="mt-5 flex flex-wrap gap-2 border-t border-border pt-4">
                                    {expense.company && <Tag icon={Building2} text={expense.company} />}
                                    {expense.project && <Tag icon={FolderKanban} text={expense.project} />}
                                    {expense.proposal && <Tag icon={FileText} text={expense.proposal} />}
                                </div>
                            )}

                            {expense.notes && <p className="mt-5 whitespace-pre-line border-t border-border pt-4 text-sm text-muted-foreground">{expense.notes}</p>}
                        </Card>

                        {/* Payment (paid / partially paid / due) */}
                        <Card className="p-5">
                            <div className="flex items-center justify-between">
                                <h2 className="flex items-center gap-2 text-sm font-semibold text-foreground"><BadgeDollarSign className="h-4 w-4" /> Payment</h2>
                                {can.recordPayment && expense.balance_due > 0 && (
                                    <Button variant="secondary" icon={Plus} onClick={() => setPayOpen(true)}>Record payment</Button>
                                )}
                            </div>

                            <div className="mt-4 flex items-center gap-2">
                                <Pill color={expense.payment_status_color} label={expense.payment_status_label} />
                                {expense.is_overdue && <span className="rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-700 dark:bg-red-900/40 dark:text-red-300">Overdue</span>}
                                {expense.due_date && <span className="ml-auto text-xs text-muted-foreground">Due {formatDate(expense.due_date)}</span>}
                            </div>

                            <div className="mt-3 h-2 w-full overflow-hidden rounded-full bg-secondary">
                                <div className="h-full rounded-full bg-emerald-500 transition-all" style={{ width: `${paidPct}%` }} />
                            </div>
                            <div className="mt-2 flex justify-between text-sm">
                                <span className="text-muted-foreground">Paid {formatCurrency(expense.amount_paid, expense.currency)} of {formatCurrency(expense.amount, expense.currency)}</span>
                                <span className="font-semibold text-foreground">{formatCurrency(expense.balance_due, expense.currency)} due</span>
                            </div>

                            {expense.payments.length > 0 && (
                                <ul className="mt-4 divide-y divide-border border-t border-border">
                                    {expense.payments.map(p => (
                                        <li key={p.id} className="flex items-center justify-between gap-3 py-2.5">
                                            <div className="min-w-0">
                                                <span className="block text-sm font-medium text-foreground">
                                                    {formatCurrency(p.amount, p.currency)}{p.method_label ? ` · ${p.method_label}` : ''}
                                                </span>
                                                <span className="block text-xs text-muted-foreground">
                                                    {formatDate(p.paid_on)}{p.reference ? ` · ${p.reference}` : ''}{p.created_by ? ` · ${p.created_by}` : ''}
                                                </span>
                                                {p.note && <span className="block text-xs text-muted-foreground">{p.note}</span>}
                                            </div>
                                            {can.recordPayment && (
                                                <button onClick={() => removePayment(p.id)} className="shrink-0 rounded-md p-1.5 text-muted-foreground transition-colors hover:bg-destructive/10 hover:text-destructive" title="Remove payment">
                                                    <Trash2 className="h-4 w-4" />
                                                </button>
                                            )}
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </Card>

                        {/* Receipts */}
                        <Card className="p-5">
                            <div className="flex items-center justify-between">
                                <h2 className="flex items-center gap-2 text-sm font-semibold text-foreground"><Paperclip className="h-4 w-4" /> Receipts</h2>
                                {can.update && (
                                    <>
                                        <input ref={fileRef} type="file" className="hidden" accept="application/pdf,image/*" onChange={onFile} />
                                        <Button variant="secondary" icon={Upload} onClick={() => fileRef.current?.click()} disabled={upload.processing}>
                                            {upload.processing ? 'Uploading…' : 'Upload'}
                                        </Button>
                                    </>
                                )}
                            </div>
                            {upload.errors.file && <p className="mt-2 text-xs text-destructive">{upload.errors.file}</p>}

                            {expense.attachments.length === 0 ? (
                                <p className="mt-4 text-sm text-muted-foreground">No receipts attached.</p>
                            ) : (
                                <ul className="mt-4 divide-y divide-border">
                                    {expense.attachments.map(a => (
                                        <li key={a.id} className="flex items-center justify-between gap-3 py-2.5">
                                            <div className="min-w-0">
                                                <span className="block truncate text-sm font-medium text-foreground">{a.display_name}</span>
                                                <span className="block text-xs text-muted-foreground">{fileSize(a.size)} · {a.uploaded_by} · {formatDate(a.created_at)}</span>
                                            </div>
                                            <div className="flex shrink-0 items-center gap-1">
                                                <a href={`/expenses/list/${expense.id}/receipts/${a.id}`} className="rounded-md p-1.5 text-muted-foreground transition-colors hover:bg-secondary hover:text-primary" title="Download">
                                                    <Download className="h-4 w-4" />
                                                </a>
                                                {can.update && (
                                                    <button onClick={() => removeReceipt(a.id)} className="rounded-md p-1.5 text-muted-foreground transition-colors hover:bg-destructive/10 hover:text-destructive" title="Remove">
                                                        <Trash2 className="h-4 w-4" />
                                                    </button>
                                                )}
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </Card>
                    </div>

                    {/* Timeline */}
                    <Card className="h-fit p-5">
                        <h2 className="text-sm font-semibold text-foreground">Timeline</h2>
                        <ol className="mt-4 space-y-4 text-sm">
                            <TimelineItem label="Submitted" at={expense.submitted_at} />
                            <TimelineItem label={expense.status === 'rejected' ? 'Rejected' : 'Approved'} at={expense.approved_at} />
                            <TimelineItem label="Reimbursed" at={expense.reimbursed_at} />
                            <TimelineItem label="Paid in full" at={expense.paid_at} />
                        </ol>
                    </Card>
                </div>
            </div>

            {editOpen && <ExpenseFormModal open onClose={() => setEditOpen(false)} expense={editable} formOptions={formOptions} />}

            <Modal
                open={rejectOpen}
                onClose={() => setRejectOpen(false)}
                title="Reject expense"
                size="sm"
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setRejectOpen(false)}>Cancel</Button>
                        <Button onClick={submitReject as unknown as () => void} disabled={reject.processing}>Reject</Button>
                    </>
                }
            >
                <form onSubmit={submitReject}>
                    <label className="label">Reason *</label>
                    <textarea className="input min-h-[80px]" value={reject.data.reason} onChange={e => reject.setData('reason', e.target.value)} autoFocus placeholder="Why is this being rejected?" />
                    {reject.errors.reason && <p className="mt-1 text-xs text-destructive">{reject.errors.reason}</p>}
                </form>
            </Modal>

            <Modal
                open={payOpen}
                onClose={() => setPayOpen(false)}
                title="Record payment"
                size="sm"
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setPayOpen(false)}>Cancel</Button>
                        <Button onClick={submitPay as unknown as () => void} disabled={pay.processing}>{pay.processing ? 'Saving…' : 'Record payment'}</Button>
                    </>
                }
            >
                <form onSubmit={submitPay} className="space-y-3">
                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <label className="label">Amount *</label>
                            <input type="number" step="0.01" min="0" className="input" value={pay.data.amount} onChange={e => pay.setData('amount', e.target.value)} autoFocus />
                            {pay.errors.amount && <p className="mt-1 text-xs text-destructive">{pay.errors.amount}</p>}
                        </div>
                        <div>
                            <label className="label">Date *</label>
                            <input type="date" className="input" value={pay.data.paid_on} onChange={e => pay.setData('paid_on', e.target.value)} />
                            {pay.errors.paid_on && <p className="mt-1 text-xs text-destructive">{pay.errors.paid_on}</p>}
                        </div>
                    </div>
                    <div>
                        <label className="label">Method</label>
                        <Select className="w-full" value={pay.data.method} onChange={v => pay.setData('method', v)} options={paymentMethods} placeholder="Not specified" />
                    </div>
                    <div>
                        <label className="label">Reference</label>
                        <input className="input" value={pay.data.reference} onChange={e => pay.setData('reference', e.target.value)} placeholder="Cheque #, transaction id…" />
                    </div>
                    <div>
                        <label className="label">Note</label>
                        <input className="input" value={pay.data.note} onChange={e => pay.setData('note', e.target.value)} />
                    </div>
                    <p className="text-xs text-muted-foreground">Balance due: {formatCurrency(expense.balance_due, expense.currency)}</p>
                </form>
            </Modal>
        </ExpenseLayout>
    );
}

function Detail({ label, value }: { label: string; value: string | null }) {
    if (!value) return null;
    return (
        <div>
            <dt className="text-xs uppercase tracking-wide text-muted-foreground/70">{label}</dt>
            <dd className="mt-0.5 text-foreground">{value}</dd>
        </div>
    );
}

function Tag({ icon: Icon, text }: { icon: React.ComponentType<{ className?: string }>; text: string }) {
    return (
        <span className="inline-flex items-center gap-1.5 rounded-full bg-secondary px-2.5 py-1 text-xs font-medium text-foreground">
            <Icon className="h-3.5 w-3.5 text-muted-foreground" /> {text}
        </span>
    );
}

function TimelineItem({ label, at }: { label: string; at: string | null }) {
    return (
        <li className="flex items-start gap-3">
            <span className={`mt-1 h-2 w-2 shrink-0 rounded-full ${at ? 'bg-primary' : 'bg-muted-foreground/30'}`} />
            <div>
                <p className={at ? 'font-medium text-foreground' : 'text-muted-foreground'}>{label}</p>
                {at && <p className="text-xs text-muted-foreground">{formatDateTime(at)}</p>}
            </div>
        </li>
    );
}
