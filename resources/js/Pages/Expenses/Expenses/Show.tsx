import { Head, Link, router, useForm } from '@inertiajs/react';
import { FormEvent, useRef, useState } from 'react';
import { ExpenseLayout } from '@/Components/layout/ExpenseLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { Pill } from '@/Components/ui/Pill';
import { Modal } from '@/Components/ui/Modal';
import { ExpenseFormModal, ExpenseFormOptions, EditableExpense } from '@/Components/expenses/ExpenseFormModal';
import { formatCurrency, formatDate, formatDateTime } from '@/Lib/utils';
import { Receipt, Pencil, Trash2, Send, Check, X, BadgeDollarSign, Upload, Download, Paperclip, Building2, FolderKanban, FileText } from 'lucide-react';

interface Attachment { id: number; display_name: string; size: number | null; mime_type: string | null; uploaded_by: string | null; created_at: string | null }

interface ExpenseDetail {
    id: number; number: string; vendor: string | null; description: string | null;
    amount: number; currency: string; payment_method: string | null; payment_method_label: string | null;
    status: string; status_label: string; status_color: string; source: string; is_billable: boolean;
    expense_date: string | null; category: string | null; category_id: number | null;
    company_id: number | null; crm_project_id: number | null; proposal_id: number | null;
    owner: string | null; notes: string | null; reject_reason: string | null;
    submitted_at: string | null; approved_at: string | null; reimbursed_at: string | null;
    approver: string | null; company: string | null; project: string | null; proposal: string | null;
    attachments: Attachment[];
}

interface Props {
    expense: ExpenseDetail;
    formOptions: ExpenseFormOptions;
    statuses: { value: string; label: string }[];
    can: { manage: boolean; update: boolean; submit: boolean; approve: boolean; reimburse: boolean; delete: boolean };
}

function fileSize(bytes: number | null): string {
    if (!bytes) return '';
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1048576) return `${(bytes / 1024).toFixed(0)} KB`;
    return `${(bytes / 1048576).toFixed(1)} MB`;
}

export default function ExpenseShow({ expense, formOptions, can }: Props) {
    const [editOpen, setEditOpen] = useState(false);
    const [rejectOpen, setRejectOpen] = useState(false);
    const fileRef = useRef<HTMLInputElement>(null);

    const post = (action: string) => router.post(`/expenses/list/${expense.id}/${action}`, {}, { preserveScroll: true });
    const reject = useForm({ reason: '' });
    const upload = useForm<{ file: File | null }>({ file: null });

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
        is_billable: expense.is_billable, category_id: expense.category_id, company_id: expense.company_id,
        crm_project_id: expense.crm_project_id, proposal_id: expense.proposal_id, notes: expense.notes,
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
