import { Head, Link, router, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import { CrmLayout } from '@/Components/layout/CrmLayout';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { Pill } from '@/Components/ui/Pill';
import { Select } from '@/Components/ui/Select';
import { NumberInput } from '@/Components/ui/NumberInput';
import { Modal, ConfirmDialog } from '@/Components/ui/Modal';
import { formatCurrency, formatDate } from '@/Lib/utils';
import { ArrowLeft, Pencil, Trash2, Plus, Building2, Mail, Phone } from 'lucide-react';

interface Item { id: number; description: string; quantity: number; unit_price: number; amount: number }
interface PaymentRow { id: number; amount: number; paid_at: string | null; method: string | null; reference: string | null; notes: string | null; recorder: string | null }
interface Invoice {
    id: number; number: string; kind: string; status: string; status_label: string; status_color: string;
    company: { id: number; name: string; email?: string | null; phone?: string | null } | null;
    project: { id: number; name: string } | null;
    issue_date: string | null; due_date: string | null;
    subtotal: number; tax_rate: number; tax_amount: number; discount_amount: number; total: number; amount_paid: number; balance: number;
    currency: string; notes: string | null; terms: string | null; owner: string | null;
    items: Item[]; payments: PaymentRow[];
}

interface Props {
    invoice: Invoice;
    statuses: Array<{ value: string; label: string; color: string }>;
    can: { manage: boolean };
}

export default function InvoiceShow({ invoice, statuses, can }: Props) {
    const [payOpen, setPayOpen] = useState(false);
    const [deleting, setDeleting] = useState(false);
    const isEstimate = invoice.kind === 'estimate';

    const changeStatus = (status: string) => {
        if (status !== invoice.status) router.post(`/crm/invoices/${invoice.id}/status`, { status }, { preserveScroll: true });
    };

    return (
        <CrmLayout>
            <Head title={`${invoice.number} · CRM`} />
            <div className="mx-auto max-w-4xl px-4 py-6 sm:px-6">
                <Link href="/crm/invoices" className="mb-4 inline-flex items-center gap-1.5 text-sm font-medium text-muted-foreground hover:text-foreground">
                    <ArrowLeft className="h-4 w-4" /> Invoices
                </Link>

                <div className="mb-5 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <div className="flex items-center gap-2.5">
                            <h1 className="font-mono text-2xl font-bold tracking-tight text-foreground">{invoice.number}</h1>
                            <Pill color={invoice.status_color} label={invoice.status_label} />
                            {isEstimate && <span className="chip">Estimate</span>}
                        </div>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {invoice.company?.name ?? 'No client'}{invoice.project ? ` · ${invoice.project.name}` : ''}
                        </p>
                    </div>
                    {can.manage && (
                        <div className="flex flex-wrap items-center gap-2">
                            <Select className="w-40" value={invoice.status} onChange={changeStatus} options={statuses.map(s => ({ value: s.value, label: s.label }))} />
                            <Button variant="secondary" icon={Pencil} href={`/crm/invoices/${invoice.id}/edit`}>Edit</Button>
                            <Button variant="danger" icon={Trash2} onClick={() => setDeleting(true)}>Delete</Button>
                        </div>
                    )}
                </div>

                <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
                    {/* Main document */}
                    <div className="space-y-4 lg:col-span-2">
                        <Card className="overflow-hidden">
                            <table className="w-full">
                                <thead className="border-b border-border bg-secondary/40">
                                    <tr>
                                        <th className="th">Description</th>
                                        <th className="th text-right">Qty</th>
                                        <th className="th text-right">Unit</th>
                                        <th className="th text-right">Amount</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border">
                                    {invoice.items.length === 0 ? (
                                        <tr><td className="td text-muted-foreground" colSpan={4}>No line items.</td></tr>
                                    ) : invoice.items.map(it => (
                                        <tr key={it.id}>
                                            <td className="td text-foreground">{it.description}</td>
                                            <td className="td text-right text-muted-foreground">{it.quantity}</td>
                                            <td className="td text-right text-muted-foreground">{formatCurrency(it.unit_price, invoice.currency)}</td>
                                            <td className="td text-right font-medium text-foreground">{formatCurrency(it.amount, invoice.currency)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                            <div className="space-y-1.5 border-t border-border px-5 py-4 text-sm">
                                <Row label="Subtotal" value={formatCurrency(invoice.subtotal, invoice.currency)} />
                                {invoice.tax_amount > 0 && <Row label={`Tax (${invoice.tax_rate}%)`} value={formatCurrency(invoice.tax_amount, invoice.currency)} />}
                                {invoice.discount_amount > 0 && <Row label="Discount" value={`- ${formatCurrency(invoice.discount_amount, invoice.currency)}`} />}
                                <div className="flex items-center justify-between border-t border-border pt-2 text-base"><span className="font-semibold text-foreground">Total</span><span className="font-bold text-foreground">{formatCurrency(invoice.total, invoice.currency)}</span></div>
                                {!isEstimate && invoice.amount_paid > 0 && <Row label="Paid" value={`- ${formatCurrency(invoice.amount_paid, invoice.currency)}`} />}
                                {!isEstimate && <div className="flex items-center justify-between pt-1"><span className="font-medium text-muted-foreground">Balance due</span><span className="font-bold text-foreground">{formatCurrency(invoice.balance, invoice.currency)}</span></div>}
                            </div>
                        </Card>

                        {(invoice.notes || invoice.terms) && (
                            <Card className="space-y-3 p-5 text-sm">
                                {invoice.notes && <div><p className="label">Notes</p><p className="whitespace-pre-line text-muted-foreground">{invoice.notes}</p></div>}
                                {invoice.terms && <div><p className="label">Terms</p><p className="whitespace-pre-line text-muted-foreground">{invoice.terms}</p></div>}
                            </Card>
                        )}
                    </div>

                    {/* Sidebar: meta + payments */}
                    <div className="space-y-4">
                        <Card className="p-5 text-sm">
                            <div className="space-y-2.5">
                                {invoice.company && (
                                    <div className="flex items-start gap-2"><Building2 className="mt-0.5 h-4 w-4 shrink-0 text-muted-foreground" /><span className="text-foreground">{invoice.company.name}</span></div>
                                )}
                                {invoice.company?.email && <div className="flex items-center gap-2"><Mail className="h-4 w-4 text-muted-foreground" /><a className="text-primary hover:underline" href={`mailto:${invoice.company.email}`}>{invoice.company.email}</a></div>}
                                {invoice.company?.phone && <div className="flex items-center gap-2"><Phone className="h-4 w-4 text-muted-foreground" /><span className="text-foreground">{invoice.company.phone}</span></div>}
                                <div className="grid grid-cols-2 gap-2 border-t border-border pt-3 text-xs">
                                    <div><p className="text-muted-foreground">{isEstimate ? 'Date' : 'Issued'}</p><p className="font-medium text-foreground">{formatDate(invoice.issue_date)}</p></div>
                                    <div><p className="text-muted-foreground">{isEstimate ? 'Valid until' : 'Due'}</p><p className="font-medium text-foreground">{formatDate(invoice.due_date)}</p></div>
                                </div>
                            </div>
                        </Card>

                        {!isEstimate && (
                            <Card className="p-5">
                                <div className="mb-3 flex items-center justify-between">
                                    <h2 className="text-sm font-bold uppercase tracking-wider text-muted-foreground/70">Payments</h2>
                                    {can.manage && <button onClick={() => setPayOpen(true)} className="inline-flex items-center gap-1 text-sm font-medium text-primary hover:underline"><Plus className="h-3.5 w-3.5" /> Record</button>}
                                </div>
                                {invoice.payments.length === 0 ? (
                                    <p className="py-2 text-sm text-muted-foreground">No payments recorded.</p>
                                ) : (
                                    <div className="space-y-2">
                                        {invoice.payments.map(p => (
                                            <div key={p.id} className="flex items-center justify-between gap-2 rounded-lg border border-border px-3 py-2 text-sm">
                                                <div className="min-w-0">
                                                    <p className="font-medium text-foreground">{formatCurrency(p.amount, invoice.currency)}</p>
                                                    <p className="truncate text-xs text-muted-foreground">{formatDate(p.paid_at)}{p.method ? ` · ${p.method}` : ''}{p.reference ? ` · ${p.reference}` : ''}</p>
                                                </div>
                                                {can.manage && (
                                                    <button onClick={() => router.delete(`/crm/invoices/${invoice.id}/payments/${p.id}`, { preserveScroll: true })} className="rounded-md p-1.5 text-muted-foreground transition-colors hover:bg-destructive/10 hover:text-destructive" title="Remove"><Trash2 className="h-4 w-4" /></button>
                                                )}
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </Card>
                        )}
                    </div>
                </div>
            </div>

            {payOpen && <PaymentModal invoiceId={invoice.id} balance={invoice.balance} onClose={() => setPayOpen(false)} />}
            <ConfirmDialog open={deleting} onClose={() => setDeleting(false)} onConfirm={() => router.delete(`/crm/invoices/${invoice.id}`)} title="Delete document?" message={<>This permanently removes <span className="font-mono font-medium text-foreground">{invoice.number}</span>.</>} />
        </CrmLayout>
    );
}

function Row({ label, value }: { label: string; value: string }) {
    return <div className="flex items-center justify-between"><span className="text-muted-foreground">{label}</span><span className="font-medium text-foreground">{value}</span></div>;
}

function PaymentModal({ invoiceId, balance, onClose }: { invoiceId: number; balance: number; onClose: () => void }) {
    const form = useForm({
        amount: balance > 0 ? String(balance) : '',
        paid_at: new Date().toISOString().slice(0, 10),
        method: 'wire',
        reference: '',
        notes: '',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post(`/crm/invoices/${invoiceId}/payments`, { preserveScroll: true, onSuccess: () => { form.reset(); onClose(); } });
    };

    return (
        <Modal open onClose={onClose} title="Record Payment"
            footer={<>
                <Button variant="ghost" onClick={onClose}>Cancel</Button>
                <Button onClick={submit as unknown as () => void} disabled={form.processing}>{form.processing ? 'Saving…' : 'Record Payment'}</Button>
            </>}>
            <form onSubmit={submit} className="space-y-4">
                <div className="grid grid-cols-2 gap-3">
                    <div>
                        <label className="label">Amount *</label>
                        <NumberInput className="input" value={form.data.amount} onChange={e => form.setData('amount', e.target.value)} autoFocus />
                        {form.errors.amount && <p className="mt-1 text-xs text-destructive">{form.errors.amount}</p>}
                    </div>
                    <div>
                        <label className="label">Date *</label>
                        <input type="date" className="input" value={form.data.paid_at} onChange={e => form.setData('paid_at', e.target.value)} />
                    </div>
                </div>
                <div className="grid grid-cols-2 gap-3">
                    <div>
                        <label className="label">Method</label>
                        <Select className="w-full" value={form.data.method} onChange={v => form.setData('method', v)} options={[
                            { value: 'wire', label: 'Wire' }, { value: 'card', label: 'Card' }, { value: 'check', label: 'Check' }, { value: 'cash', label: 'Cash' }, { value: 'other', label: 'Other' },
                        ]} />
                    </div>
                    <div>
                        <label className="label">Reference</label>
                        <input className="input" value={form.data.reference} onChange={e => form.setData('reference', e.target.value)} placeholder="Txn / check #" />
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
