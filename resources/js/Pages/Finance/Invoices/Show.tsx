import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { FinanceLayout } from '@/Components/layout/FinanceLayout';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { Pill } from '@/Components/ui/Pill';
import { CollectPaymentModal } from '@/Components/finance/CollectPaymentModal';
import { RecordPaymentModal } from '@/Components/finance/RecordPaymentModal';
import { formatCurrency } from '@/Lib/utils';
import { ArrowLeft, ReceiptText, CreditCard, BadgeDollarSign, ExternalLink, CheckCircle2 } from 'lucide-react';

interface Item { id: number; description: string; quantity: number; unit_price: number; amount: number }
interface Payment { id: number; amount: number; method: string | null; reference: string | null; status: string; paid_at: string | null }
interface Intent { id: number; provider: string; amount: number; status: string; status_label: string; status_color: string; checkout_url: string | null; reference: string | null }
interface Invoice {
    id: number; number: string; status: string; status_label: string; status_color: string;
    company: string | null; company_email: string | null; issue_date: string | null; due_date: string | null; currency: string;
    subtotal: number; tax_amount: number; discount_amount: number; total: number; amount_paid: number; balance: number;
    notes: string | null; terms: string | null; items: Item[]; payments: Payment[];
}

interface Props {
    invoice: Invoice;
    intents: Intent[];
    provider: string;
    can: { pay: boolean };
}

export default function FinanceInvoiceShow({ invoice, intents, provider, can }: Props) {
    const [collectOpen, setCollectOpen] = useState(false);
    const [recordOpen, setRecordOpen] = useState(false);

    const capture = (intentId: number) => router.post(`/finance/invoices/${invoice.id}/intents/${intentId}/capture`, {}, { preserveScroll: true });
    const paid = invoice.balance <= 0;

    return (
        <FinanceLayout>
            <Head title={`${invoice.number} · Finance`} />
            <div className="mx-auto max-w-4xl px-4 py-6 sm:px-6">
                <Link href="/finance/invoices" className="mb-4 inline-flex items-center gap-1.5 text-sm font-medium text-muted-foreground hover:text-foreground">
                    <ArrowLeft className="h-4 w-4" /> Invoices
                </Link>

                <div className="card-surface mb-6 p-6">
                    <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div className="flex items-center gap-4">
                            <div className="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-500 text-white"><ReceiptText className="h-7 w-7" /></div>
                            <div>
                                <h1 className="font-mono text-2xl font-bold tracking-tight text-foreground">{invoice.number}</h1>
                                <div className="mt-1 flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                                    <Pill color={invoice.status_color} label={invoice.status_label} />
                                    {invoice.company && <span>{invoice.company}</span>}
                                    {invoice.due_date && <span className="chip">Due {invoice.due_date}</span>}
                                </div>
                            </div>
                        </div>
                        {can.pay && !paid && (
                            <div className="flex flex-wrap items-center gap-2">
                                <Button variant="secondary" size="sm" icon={CreditCard} onClick={() => setCollectOpen(true)}>Collect online</Button>
                                <Button variant="success" size="sm" icon={BadgeDollarSign} onClick={() => setRecordOpen(true)}>Record payment</Button>
                            </div>
                        )}
                        {paid && <span className="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-3 py-1 text-sm font-semibold text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300"><CheckCircle2 className="h-4 w-4" /> Paid in full</span>}
                    </div>

                    <div className="mt-5 grid grid-cols-2 gap-4 border-t border-border pt-4 sm:grid-cols-4">
                        <Metric label="Total" value={formatCurrency(invoice.total, invoice.currency)} />
                        <Metric label="Paid" value={formatCurrency(invoice.amount_paid, invoice.currency)} />
                        <Metric label="Balance" value={formatCurrency(invoice.balance, invoice.currency)} highlight={invoice.balance > 0} />
                        <Metric label="Issued" value={invoice.issue_date ?? '—'} />
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <Card className="overflow-hidden lg:col-span-2">
                        <div className="border-b border-border px-5 py-3"><h2 className="text-sm font-bold uppercase tracking-wider text-muted-foreground/70">Line items</h2></div>
                        {invoice.items.length === 0 ? (
                            <p className="px-5 py-4 text-sm text-muted-foreground">No line items.</p>
                        ) : (
                            <table className="w-full text-sm">
                                <thead className="bg-secondary/40 text-left text-xs uppercase text-muted-foreground/70">
                                    <tr><th className="px-5 py-2">Description</th><th className="px-5 py-2 text-right">Qty</th><th className="px-5 py-2 text-right">Unit</th><th className="px-5 py-2 text-right">Amount</th></tr>
                                </thead>
                                <tbody className="divide-y divide-border">
                                    {invoice.items.map(it => (
                                        <tr key={it.id}>
                                            <td className="px-5 py-2.5 text-foreground">{it.description}</td>
                                            <td className="px-5 py-2.5 text-right text-muted-foreground">{it.quantity}</td>
                                            <td className="px-5 py-2.5 text-right text-muted-foreground">{formatCurrency(it.unit_price, invoice.currency)}</td>
                                            <td className="px-5 py-2.5 text-right font-medium text-foreground">{formatCurrency(it.amount, invoice.currency)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}
                        <div className="flex justify-end border-t border-border px-5 py-4">
                            <div className="w-full max-w-xs space-y-1.5 text-sm">
                                <div className="flex justify-between"><span className="text-muted-foreground">Subtotal</span><span className="text-foreground">{formatCurrency(invoice.subtotal, invoice.currency)}</span></div>
                                <div className="flex justify-between"><span className="text-muted-foreground">Tax</span><span className="text-foreground">{formatCurrency(invoice.tax_amount, invoice.currency)}</span></div>
                                {invoice.discount_amount > 0 && <div className="flex justify-between"><span className="text-muted-foreground">Discount</span><span className="text-foreground">−{formatCurrency(invoice.discount_amount, invoice.currency)}</span></div>}
                                <div className="flex justify-between border-t border-border pt-1.5 text-base font-bold text-foreground"><span>Total</span><span>{formatCurrency(invoice.total, invoice.currency)}</span></div>
                            </div>
                        </div>
                    </Card>

                    <div className="space-y-4">
                        <Card className="p-5">
                            <h2 className="mb-3 text-sm font-bold uppercase tracking-wider text-muted-foreground/70">Payments</h2>
                            {invoice.payments.length === 0 ? (
                                <p className="text-sm text-muted-foreground">No payments yet.</p>
                            ) : (
                                <div className="space-y-2">
                                    {invoice.payments.map(p => (
                                        <div key={p.id} className="flex items-center justify-between gap-2 text-sm">
                                            <span className="min-w-0">
                                                <span className="block font-medium text-foreground">{formatCurrency(p.amount, invoice.currency)}</span>
                                                <span className="block truncate text-xs text-muted-foreground">{p.method}{p.paid_at ? ` · ${p.paid_at}` : ''}</span>
                                            </span>
                                            <Pill color={p.status === 'completed' ? 'green' : 'amber'} label={p.status} />
                                        </div>
                                    ))}
                                </div>
                            )}
                        </Card>

                        {intents.length > 0 && (
                            <Card className="p-5">
                                <h2 className="mb-3 text-sm font-bold uppercase tracking-wider text-muted-foreground/70">Payment links</h2>
                                <div className="space-y-2">
                                    {intents.map(i => (
                                        <div key={i.id} className="rounded-lg border border-border p-3 text-sm">
                                            <div className="flex items-center justify-between gap-2">
                                                <span className="font-medium text-foreground">{formatCurrency(i.amount, invoice.currency)}</span>
                                                <Pill color={i.status_color} label={i.status_label} />
                                            </div>
                                            <div className="mt-1.5 flex items-center justify-between gap-2">
                                                <span className="truncate text-xs capitalize text-muted-foreground">{i.provider}</span>
                                                <span className="flex items-center gap-2">
                                                    {i.checkout_url && i.status === 'pending' && <a href={i.checkout_url} target="_blank" rel="noreferrer" className="inline-flex items-center gap-1 text-xs text-primary hover:underline"><ExternalLink className="h-3 w-3" /> Link</a>}
                                                    {can.pay && i.status === 'pending' && <button onClick={() => capture(i.id)} className="rounded-md bg-emerald-600 px-2 py-1 text-xs font-medium text-white hover:bg-emerald-700">Mark paid</button>}
                                                </span>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </Card>
                        )}
                    </div>
                </div>
            </div>

            {collectOpen && <CollectPaymentModal open onClose={() => setCollectOpen(false)} invoiceId={invoice.id} balance={invoice.balance} currency={invoice.currency} provider={provider} />}
            {recordOpen && <RecordPaymentModal open onClose={() => setRecordOpen(false)} invoiceId={invoice.id} balance={invoice.balance} currency={invoice.currency} />}
        </FinanceLayout>
    );
}

function Metric({ label, value, highlight }: { label: string; value: string; highlight?: boolean }) {
    return (
        <div>
            <p className="text-xs text-muted-foreground">{label}</p>
            <p className={highlight ? 'mt-0.5 text-lg font-bold text-amber-600' : 'mt-0.5 text-lg font-bold text-foreground'}>{value}</p>
        </div>
    );
}
