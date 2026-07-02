import { Head, Link, router, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import { ProcurementLayout } from '@/Components/layout/ProcurementLayout';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { Pill } from '@/Components/ui/Pill';
import { AttachmentsPanel, Attachment } from '@/Components/procurement/AttachmentsPanel';
import { ApprovalPanel, ApprovalData } from '@/Components/procurement/ApprovalPanel';
import { formatCurrency } from '@/Lib/utils';
import { ArrowLeft, Receipt, Trash2, RefreshCw, Check, X, FileText } from 'lucide-react';

interface Item { id: number; description: string; sku: string | null; unit: string | null; quantity: number; unit_cost: number; tax_rate: number; line_total: number }
interface Payment {
    id: number; amount: number; payment_method: string | null; paid_on: string | null; reference: string | null; note: string | null;
    approval_status: string; approval_status_label: string; approval_status_color: string; recorded_by: string | null; approved_by: string | null;
    approval: ApprovalData | null;
}
interface Bill {
    id: number; number: string; vendor_invoice_number: string | null;
    supplier: { id: number | null; name: string | null };
    purchase_order: { id: number; number: string } | null;
    payment_status: string; payment_status_label: string; payment_status_color: string;
    bill_date: string | null; due_date: string | null; currency: string;
    subtotal: number; tax_amount: number; shipping_amount: number; discount_total: number; total: number; amount_paid: number; balance_due: number;
    recurring: boolean; recurring_frequency: string | null; recurring_cycles: number; recurring_total_cycles: number; next_recurring_date: string | null;
    notes: string | null; terms: string | null; items: Item[]; payments: Payment[];
}
interface Props { bill: Bill; can: { manage: boolean; approvePayments: boolean }; pdf_url: string; attachments: Attachment[] }

export default function BillShow({ bill, can, pdf_url, attachments }: Props) {
    const pay = useForm({ amount: '', payment_method: '', paid_on: new Date().toISOString().slice(0, 10), reference: '', note: '', require_approval: false });
    const submitPayment = (e: FormEvent) => {
        e.preventDefault();
        pay.post(`/procurement/bills/${bill.id}/payments`, { preserveScroll: true, onSuccess: () => pay.reset('amount', 'reference', 'note') });
    };
    const decide = (paymentId: number, action: 'approve' | 'reject') =>
        router.post(`/procurement/bills/${bill.id}/payments/${paymentId}/${action}`, {}, { preserveScroll: true });

    return (
        <ProcurementLayout>
            <Head title={`${bill.number} · Bill`} />
            <div className="mx-auto max-w-5xl px-4 py-6 sm:px-6">
                <Link href="/procurement/bills" className="mb-4 inline-flex items-center gap-1.5 text-sm font-medium text-muted-foreground hover:text-foreground">
                    <ArrowLeft className="h-4 w-4" /> Bills
                </Link>

                <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                    <div className="flex items-center gap-3">
                        <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-brand-gradient text-white"><Receipt className="h-5 w-5" /></div>
                        <div>
                            <div className="flex items-center gap-2">
                                <h1 className="font-mono text-xl font-bold text-foreground">{bill.number}</h1>
                                <Pill color={bill.payment_status_color} label={bill.payment_status_label} />
                                {bill.recurring && <span className="inline-flex items-center gap-1 text-xs text-muted-foreground"><RefreshCw className="h-3 w-3" /> {bill.recurring_frequency}</span>}
                            </div>
                            <p className="text-sm text-muted-foreground">{bill.supplier.name}{bill.vendor_invoice_number ? ` · invoice ${bill.vendor_invoice_number}` : ''}</p>
                        </div>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <a href={pdf_url} target="_blank" rel="noopener"><Button variant="ghost" icon={FileText}>PDF</Button></a>
                        {can.manage && <Button variant="ghost" icon={Trash2} onClick={() => confirm('Delete this bill?') && router.delete(`/procurement/bills/${bill.id}`)}>Delete</Button>}
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
                    <div className="space-y-4 lg:col-span-2">
                        <Card className="p-5">
                            <dl className="grid grid-cols-2 gap-x-4 gap-y-3 text-sm sm:grid-cols-3">
                                <Field label="Vendor" value={bill.supplier.name} />
                                <Field label="Bill date" value={bill.bill_date} />
                                <Field label="Due date" value={bill.due_date} />
                                {bill.purchase_order && <div><dt className="text-xs uppercase tracking-wide text-muted-foreground/70">From PO</dt><dd className="mt-0.5"><Link className="font-mono text-primary hover:underline" href={`/procurement/purchase-orders/${bill.purchase_order.id}`}>{bill.purchase_order.number}</Link></dd></div>}
                                {bill.recurring && <Field label="Next recurring" value={bill.next_recurring_date} />}
                                {bill.recurring && <Field label="Cycles" value={`${bill.recurring_cycles}${bill.recurring_total_cycles ? ` / ${bill.recurring_total_cycles}` : ''}`} />}
                            </dl>
                        </Card>

                        <Card className="overflow-hidden">
                            <div className="border-b border-border px-5 py-3"><h2 className="text-sm font-bold uppercase tracking-wider text-muted-foreground/70">Items</h2></div>
                            <table className="w-full text-sm">
                                <thead className="bg-secondary/40 text-left text-xs uppercase text-muted-foreground/70">
                                    <tr><th className="px-4 py-2">Description</th><th className="px-3 py-2 text-right">Qty</th><th className="px-3 py-2 text-right">Unit cost</th><th className="px-4 py-2 text-right">Line total</th></tr>
                                </thead>
                                <tbody className="divide-y divide-border">
                                    {bill.items.map(i => (
                                        <tr key={i.id}>
                                            <td className="px-4 py-2 text-foreground">{i.description}{i.sku && <span className="ml-1 text-xs text-muted-foreground">· {i.sku}</span>}</td>
                                            <td className="px-3 py-2 text-right text-muted-foreground">{i.quantity}{i.unit ? ` ${i.unit}` : ''}</td>
                                            <td className="px-3 py-2 text-right text-muted-foreground">{formatCurrency(i.unit_cost, bill.currency)}</td>
                                            <td className="px-4 py-2 text-right font-medium text-foreground">{formatCurrency(i.line_total, bill.currency)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </Card>

                        <Card className="overflow-hidden">
                            <div className="border-b border-border px-5 py-3"><h2 className="text-sm font-bold uppercase tracking-wider text-muted-foreground/70">Payments</h2></div>
                            {bill.payments.length === 0 ? (
                                <p className="px-5 py-4 text-sm text-muted-foreground">No payments recorded yet.</p>
                            ) : (
                                <div className="divide-y divide-border">
                                    {bill.payments.map(p => {
                                        const chainActive = p.approval?.status === 'pending';
                                        return (
                                        <div key={p.id} className="px-5 py-3 text-sm">
                                            <div className="flex flex-wrap items-center justify-between gap-2">
                                                <div>
                                                    <span className="font-medium text-foreground">{formatCurrency(p.amount, bill.currency)}</span>
                                                    <span className="ml-2 text-muted-foreground">{p.paid_on}{p.payment_method ? ` · ${p.payment_method}` : ''}{p.reference ? ` · ${p.reference}` : ''}</span>
                                                    {p.note && <div className="text-xs text-muted-foreground">{p.note}</div>}
                                                </div>
                                                <div className="flex items-center gap-2">
                                                    <Pill color={p.approval_status_color} label={p.approval_status_label} />
                                                    {p.approval_status === 'pending' && can.approvePayments && !chainActive && (
                                                        <>
                                                            <button onClick={() => decide(p.id, 'approve')} className="rounded-md p-1.5 text-green-600 hover:bg-green-500/10" title="Approve"><Check className="h-4 w-4" /></button>
                                                            <button onClick={() => decide(p.id, 'reject')} className="rounded-md p-1.5 text-destructive hover:bg-destructive/10" title="Reject"><X className="h-4 w-4" /></button>
                                                        </>
                                                    )}
                                                </div>
                                            </div>
                                            {p.approval && <ApprovalPanel entity="bill-payments" id={p.id} approval={p.approval} compact />}
                                        </div>
                                        );
                                    })}
                                </div>
                            )}
                        </Card>
                    </div>

                    <div className="space-y-4">
                        <Card className="p-5">
                            <div className="space-y-2 text-sm">
                                <Row label="Subtotal" value={formatCurrency(bill.subtotal, bill.currency)} />
                                <Row label="Tax" value={formatCurrency(bill.tax_amount, bill.currency)} />
                                {bill.shipping_amount > 0 && <Row label="Shipping" value={formatCurrency(bill.shipping_amount, bill.currency)} />}
                                {bill.discount_total > 0 && <Row label="Discount" value={`− ${formatCurrency(bill.discount_total, bill.currency)}`} />}
                                <div className="flex items-center justify-between border-t border-border pt-2 text-base font-bold text-foreground"><span>Total</span><span>{formatCurrency(bill.total, bill.currency)}</span></div>
                                <Row label="Paid" value={formatCurrency(bill.amount_paid, bill.currency)} />
                                <div className="flex items-center justify-between font-semibold text-foreground"><span>Balance due</span><span>{formatCurrency(bill.balance_due, bill.currency)}</span></div>
                            </div>
                        </Card>

                        {can.manage && bill.payment_status !== 'paid' && (
                            <Card className="p-5">
                                <h2 className="mb-2 text-xs font-bold uppercase tracking-wider text-muted-foreground/70">Record payment</h2>
                                <form onSubmit={submitPayment} className="space-y-2">
                                    <input type="number" step="0.01" min="0" className="input" placeholder="Amount *" value={pay.data.amount} onChange={e => pay.setData('amount', e.target.value)} />
                                    {pay.errors.amount && <p className="text-xs text-destructive">{pay.errors.amount}</p>}
                                    <input type="date" className="input" value={pay.data.paid_on} onChange={e => pay.setData('paid_on', e.target.value)} />
                                    <input className="input" placeholder="Method (e.g. bank transfer)" value={pay.data.payment_method} onChange={e => pay.setData('payment_method', e.target.value)} />
                                    <input className="input" placeholder="Reference / transaction #" value={pay.data.reference} onChange={e => pay.setData('reference', e.target.value)} />
                                    {can.approvePayments && (
                                        <label className="flex items-center gap-2 text-xs text-muted-foreground">
                                            <input type="checkbox" className="h-3.5 w-3.5 rounded border-border" checked={pay.data.require_approval} onChange={e => pay.setData('require_approval', e.target.checked)} />
                                            Require approval before it counts
                                        </label>
                                    )}
                                    <Button type="submit" className="w-full" disabled={pay.processing}>{pay.processing ? 'Saving…' : 'Record payment'}</Button>
                                </form>
                                {!can.approvePayments && <p className="mt-2 text-xs text-muted-foreground">Payments you record are submitted for approval.</p>}
                            </Card>
                        )}

                        {bill.terms && <Card className="p-5"><h2 className="mb-1 text-xs font-bold uppercase tracking-wider text-muted-foreground/70">Terms</h2><p className="whitespace-pre-line text-sm text-muted-foreground">{bill.terms}</p></Card>}

                        <AttachmentsPanel entity="bills" id={bill.id} attachments={attachments} canManage={can.manage} />
                    </div>
                </div>
            </div>
        </ProcurementLayout>
    );
}

function Field({ label, value }: { label: string; value: string | null }) {
    return <div><dt className="text-xs uppercase tracking-wide text-muted-foreground/70">{label}</dt><dd className="mt-0.5 text-foreground">{value ?? '—'}</dd></div>;
}
function Row({ label, value }: { label: string; value: string }) {
    return <div className="flex items-center justify-between"><span className="text-muted-foreground">{label}</span><span className="text-foreground">{value}</span></div>;
}
