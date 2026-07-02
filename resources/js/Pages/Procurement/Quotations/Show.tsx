import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { ProcurementLayout } from '@/Components/layout/ProcurementLayout';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { Pill } from '@/Components/ui/Pill';
import { SendDocumentModal, SendMeta } from '@/Components/procurement/SendDocumentModal';
import { AttachmentsPanel, Attachment } from '@/Components/procurement/AttachmentsPanel';
import { formatCurrency } from '@/Lib/utils';
import { ArrowLeft, FileText, Send, Check, X, ShoppingCart, Trash2, Mail } from 'lucide-react';

interface Item { id: number; description: string; sku: string | null; unit: string | null; quantity: number; unit_cost: number; tax_rate: number; line_total: number }
interface LinkedDoc { id: number; number: string; status: string; status_label: string; status_color: string; total: number; currency: string }
interface Quotation {
    id: number; number: string; reference_no: string | null;
    supplier: { id: number | null; name: string | null };
    purchase_request: { id: number; number: string } | null;
    status: string; status_label: string; status_color: string; is_editable: boolean; can_accept: boolean;
    quote_date: string | null; expiry_date: string | null; currency: string;
    subtotal: number; tax_amount: number; discount_total: number; total: number;
    vendor_note: string | null; admin_note: string | null; terms: string | null;
    items: Item[]; purchase_orders: LinkedDoc[];
}
interface Props { quotation: Quotation; can: { manage: boolean; createOrder: boolean }; send: SendMeta & { sent_at: string | null }; attachments: Attachment[] }

export default function QuotationShow({ quotation: q, can, send, attachments }: Props) {
    const [sendOpen, setSendOpen] = useState(false);
    const post = (action: string) => router.post(`/procurement/quotations/${q.id}/${action}`, {}, { preserveScroll: true });

    return (
        <ProcurementLayout>
            <Head title={`${q.number} · Quotation`} />
            <div className="mx-auto max-w-5xl px-4 py-6 sm:px-6">
                <Link href="/procurement/quotations" className="mb-4 inline-flex items-center gap-1.5 text-sm font-medium text-muted-foreground hover:text-foreground">
                    <ArrowLeft className="h-4 w-4" /> Quotations
                </Link>

                <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                    <div className="flex items-center gap-3">
                        <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-brand-gradient text-white"><FileText className="h-5 w-5" /></div>
                        <div>
                            <div className="flex items-center gap-2"><h1 className="font-mono text-xl font-bold text-foreground">{q.number}</h1><Pill color={q.status_color} label={q.status_label} /></div>
                            <p className="text-sm text-muted-foreground">{q.supplier.name}</p>
                        </div>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        {can.manage && <Button icon={Mail} onClick={() => setSendOpen(true)}>Email vendor</Button>}
                        <a href={send.pdf_url} target="_blank" rel="noopener"><Button variant="ghost" icon={FileText}>PDF</Button></a>
                        {q.status === 'draft' && can.manage && <Button variant="secondary" icon={Send} onClick={() => post('send')}>Mark sent</Button>}
                        {q.status === 'sent' && can.manage && <Button variant="secondary" icon={Check} onClick={() => post('received')}>Mark received</Button>}
                        {q.can_accept && can.createOrder && <Button icon={ShoppingCart} onClick={() => post('accept')}>Accept → PO</Button>}
                        {q.is_editable && can.manage && <Button variant="ghost" icon={X} onClick={() => post('reject')}>Reject</Button>}
                        {q.is_editable && can.manage && <Button variant="ghost" icon={Trash2} onClick={() => confirm('Delete this quotation?') && router.delete(`/procurement/quotations/${q.id}`)}>Delete</Button>}
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
                    <div className="space-y-4 lg:col-span-2">
                        <Card className="p-5">
                            <dl className="grid grid-cols-2 gap-x-4 gap-y-3 text-sm sm:grid-cols-3">
                                <Field label="Vendor" value={q.supplier.name} />
                                <Field label="Reference #" value={q.reference_no} />
                                <Field label="Quote date" value={q.quote_date} />
                                <Field label="Expiry" value={q.expiry_date} />
                                {q.purchase_request && <div><dt className="text-xs uppercase tracking-wide text-muted-foreground/70">From request</dt><dd className="mt-0.5"><Link className="font-mono text-primary hover:underline" href={`/procurement/purchase-requests/${q.purchase_request.id}`}>{q.purchase_request.number}</Link></dd></div>}
                            </dl>
                        </Card>

                        <Card className="overflow-hidden">
                            <div className="border-b border-border px-5 py-3"><h2 className="text-sm font-bold uppercase tracking-wider text-muted-foreground/70">Items</h2></div>
                            <table className="w-full text-sm">
                                <thead className="bg-secondary/40 text-left text-xs uppercase text-muted-foreground/70">
                                    <tr><th className="px-4 py-2">Description</th><th className="px-3 py-2 text-right">Qty</th><th className="px-3 py-2 text-right">Unit cost</th><th className="px-4 py-2 text-right">Line total</th></tr>
                                </thead>
                                <tbody className="divide-y divide-border">
                                    {q.items.map(i => (
                                        <tr key={i.id}>
                                            <td className="px-4 py-2 text-foreground">{i.description}{i.sku && <span className="ml-1 text-xs text-muted-foreground">· {i.sku}</span>}</td>
                                            <td className="px-3 py-2 text-right text-muted-foreground">{i.quantity}{i.unit ? ` ${i.unit}` : ''}</td>
                                            <td className="px-3 py-2 text-right text-muted-foreground">{formatCurrency(i.unit_cost, q.currency)}</td>
                                            <td className="px-4 py-2 text-right font-medium text-foreground">{formatCurrency(i.line_total, q.currency)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </Card>

                        {q.terms && <Card className="p-5"><h2 className="mb-1 text-xs font-bold uppercase tracking-wider text-muted-foreground/70">Terms</h2><p className="whitespace-pre-line text-sm text-muted-foreground">{q.terms}</p></Card>}
                    </div>

                    <div className="space-y-4">
                        <Card className="p-5">
                            <div className="space-y-2 text-sm">
                                <Row label="Subtotal" value={formatCurrency(q.subtotal, q.currency)} />
                                <Row label="Tax" value={formatCurrency(q.tax_amount, q.currency)} />
                                {q.discount_total > 0 && <Row label="Discount" value={`− ${formatCurrency(q.discount_total, q.currency)}`} />}
                                <div className="flex items-center justify-between border-t border-border pt-2 text-base font-bold text-foreground"><span>Total</span><span>{formatCurrency(q.total, q.currency)}</span></div>
                            </div>
                        </Card>
                        <AttachmentsPanel entity="quotations" id={q.id} attachments={attachments} canManage={can.manage} />
                        {q.purchase_orders.length > 0 && (
                            <Card className="p-5">
                                <h2 className="mb-2 text-xs font-bold uppercase tracking-wider text-muted-foreground/70">Purchase Orders</h2>
                                <div className="space-y-2">
                                    {q.purchase_orders.map(d => (
                                        <Link key={d.id} href={`/procurement/purchase-orders/${d.id}`} className="flex items-center justify-between rounded-lg border border-border p-2.5 text-sm hover:bg-secondary/50">
                                            <span className="font-mono text-foreground">{d.number}</span>
                                            <span className="flex items-center gap-2"><Pill color={d.status_color} label={d.status_label} /><span className="text-muted-foreground">{formatCurrency(d.total, d.currency)}</span></span>
                                        </Link>
                                    ))}
                                </div>
                            </Card>
                        )}
                    </div>
                </div>
            </div>
            <SendDocumentModal open={sendOpen} onClose={() => setSendOpen(false)} meta={send} kindLabel="request for quotation" />
        </ProcurementLayout>
    );
}

function Field({ label, value }: { label: string; value: string | null }) {
    return <div><dt className="text-xs uppercase tracking-wide text-muted-foreground/70">{label}</dt><dd className="mt-0.5 text-foreground">{value ?? '—'}</dd></div>;
}
function Row({ label, value }: { label: string; value: string }) {
    return <div className="flex items-center justify-between"><span className="text-muted-foreground">{label}</span><span className="text-foreground">{value}</span></div>;
}
