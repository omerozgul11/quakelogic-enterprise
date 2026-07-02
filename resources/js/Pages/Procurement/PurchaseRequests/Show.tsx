import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { ProcurementLayout } from '@/Components/layout/ProcurementLayout';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { Pill } from '@/Components/ui/Pill';
import { Select } from '@/Components/ui/Select';
import { SendDocumentModal, SendMeta } from '@/Components/procurement/SendDocumentModal';
import { AttachmentsPanel, Attachment } from '@/Components/procurement/AttachmentsPanel';
import { ApprovalPanel, ApprovalData } from '@/Components/procurement/ApprovalPanel';
import { formatCurrency } from '@/Lib/utils';
import { ArrowLeft, ClipboardList, Send, Check, X, FileText, ShoppingCart, Trash2, Mail } from 'lucide-react';

interface Item { id: number; description: string; sku: string | null; unit: string | null; quantity: number; unit_cost: number; tax_rate: number; line_total: number }
interface LinkedDoc { id: number; number: string; supplier?: string | null; status: string; status_label: string; status_color: string; total: number; currency: string }
interface PR {
    id: number; number: string; title: string; description: string | null; department: string | null;
    requester: string | null; project: string | null;
    status: string; status_label: string; status_color: string; is_editable: boolean; can_convert: boolean;
    currency: string; subtotal: number; tax_amount: number; total: number;
    notes: string | null; rejected_reason: string | null; approved_by: string | null; approved_at: string | null;
    items: Item[]; quotations: LinkedDoc[]; purchase_orders: LinkedDoc[];
}
interface Props {
    request: PR;
    suppliers: { id: number; name: string }[];
    can: { manage: boolean; approve: boolean; createQuotation: boolean; createOrder: boolean };
    send: SendMeta & { sent_at: string | null };
    attachments: Attachment[];
    approval: ApprovalData | null;
}

export default function PurchaseRequestShow({ request: pr, suppliers, can, send, attachments, approval }: Props) {
    const chainActive = approval?.status === 'pending';
    const [supplierId, setSupplierId] = useState('');
    const [sendOpen, setSendOpen] = useState(false);
    const reject = useForm({ reason: '' });
    const post = (url: string) => router.post(url, {}, { preserveScroll: true });
    const convert = (kind: 'quotation' | 'order') => {
        if (!supplierId) return;
        router.post(`/procurement/purchase-requests/${pr.id}/convert-to-${kind}`, { procurement_supplier_id: supplierId }, { preserveScroll: true });
    };
    const supplierOpts = suppliers.map(s => ({ value: String(s.id), label: s.name }));

    return (
        <ProcurementLayout>
            <Head title={`${pr.number} · Purchase Request`} />
            <div className="mx-auto max-w-5xl px-4 py-6 sm:px-6">
                <Link href="/procurement/purchase-requests" className="mb-4 inline-flex items-center gap-1.5 text-sm font-medium text-muted-foreground hover:text-foreground">
                    <ArrowLeft className="h-4 w-4" /> Purchase Requests
                </Link>

                <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                    <div className="flex items-center gap-3">
                        <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-brand-gradient text-white"><ClipboardList className="h-5 w-5" /></div>
                        <div>
                            <div className="flex items-center gap-2">
                                <h1 className="font-mono text-xl font-bold text-foreground">{pr.number}</h1>
                                <Pill color={pr.status_color} label={pr.status_label} />
                            </div>
                            <p className="text-sm text-muted-foreground">{pr.title}</p>
                        </div>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        {can.manage && <Button variant="secondary" icon={Mail} onClick={() => setSendOpen(true)}>Email</Button>}
                        <a href={send.pdf_url} target="_blank" rel="noopener"><Button variant="ghost" icon={FileText}>PDF</Button></a>
                        {pr.status === 'draft' && can.manage && <Button icon={Send} onClick={() => post(`/procurement/purchase-requests/${pr.id}/submit`)}>Submit for approval</Button>}
                        {pr.status === 'pending_approval' && can.approve && !chainActive && (
                            <>
                                <Button icon={Check} onClick={() => post(`/procurement/purchase-requests/${pr.id}/approve`)}>Approve</Button>
                                <Button variant="ghost" icon={X} onClick={() => reject.post(`/procurement/purchase-requests/${pr.id}/reject`, { preserveScroll: true })}>Reject</Button>
                            </>
                        )}
                        {pr.status === 'draft' && can.manage && (
                            <Button variant="ghost" icon={Trash2} onClick={() => confirm('Delete this request?') && router.delete(`/procurement/purchase-requests/${pr.id}`)}>Delete</Button>
                        )}
                    </div>
                </div>

                {pr.status === 'rejected' && pr.rejected_reason && (
                    <Card className="mb-4 border-destructive/40 bg-destructive/5 p-4 text-sm text-destructive">Rejected: {pr.rejected_reason}</Card>
                )}

                <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
                    <div className="space-y-4 lg:col-span-2">
                        <Card className="p-5">
                            <dl className="grid grid-cols-2 gap-x-4 gap-y-3 text-sm sm:grid-cols-3">
                                <Field label="Requester" value={pr.requester} />
                                <Field label="Department" value={pr.department} />
                                <Field label="Project" value={pr.project} />
                                {pr.approved_by && <Field label="Approved by" value={pr.approved_by} />}
                            </dl>
                            {pr.description && <p className="mt-4 whitespace-pre-line border-t border-border pt-3 text-sm text-muted-foreground">{pr.description}</p>}
                        </Card>

                        <Card className="overflow-hidden">
                            <div className="border-b border-border px-5 py-3"><h2 className="text-sm font-bold uppercase tracking-wider text-muted-foreground/70">Items</h2></div>
                            <table className="w-full text-sm">
                                <thead className="bg-secondary/40 text-left text-xs uppercase text-muted-foreground/70">
                                    <tr><th className="px-4 py-2">Description</th><th className="px-3 py-2 text-right">Qty</th><th className="px-3 py-2 text-right">Unit cost</th><th className="px-4 py-2 text-right">Line total</th></tr>
                                </thead>
                                <tbody className="divide-y divide-border">
                                    {pr.items.map(i => (
                                        <tr key={i.id}>
                                            <td className="px-4 py-2 text-foreground">{i.description}{i.sku && <span className="ml-1 text-xs text-muted-foreground">· {i.sku}</span>}</td>
                                            <td className="px-3 py-2 text-right text-muted-foreground">{i.quantity}{i.unit ? ` ${i.unit}` : ''}</td>
                                            <td className="px-3 py-2 text-right text-muted-foreground">{formatCurrency(i.unit_cost, pr.currency)}</td>
                                            <td className="px-4 py-2 text-right font-medium text-foreground">{formatCurrency(i.line_total, pr.currency)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </Card>

                        {pr.notes && <Card className="p-5"><h2 className="mb-1 text-xs font-bold uppercase tracking-wider text-muted-foreground/70">Notes</h2><p className="whitespace-pre-line text-sm text-muted-foreground">{pr.notes}</p></Card>}
                    </div>

                    <div className="space-y-4">
                        <Card className="p-5">
                            <div className="space-y-2 text-sm">
                                <Row label="Subtotal" value={formatCurrency(pr.subtotal, pr.currency)} />
                                <Row label="Tax" value={formatCurrency(pr.tax_amount, pr.currency)} />
                                <div className="flex items-center justify-between border-t border-border pt-2 text-base font-bold text-foreground"><span>Total</span><span>{formatCurrency(pr.total, pr.currency)}</span></div>
                            </div>
                        </Card>

                        {pr.can_convert && (can.createQuotation || can.createOrder) && (
                            <Card className="p-5">
                                <h2 className="mb-2 text-xs font-bold uppercase tracking-wider text-muted-foreground/70">Convert</h2>
                                <Select className="w-full" value={supplierId} placeholder="Select vendor…" searchable onChange={setSupplierId} options={supplierOpts} />
                                <div className="mt-3 flex flex-col gap-2">
                                    {can.createQuotation && <Button variant="secondary" icon={FileText} disabled={!supplierId} onClick={() => convert('quotation')}>Request quotation</Button>}
                                    {can.createOrder && <Button icon={ShoppingCart} disabled={!supplierId} onClick={() => convert('order')}>Create purchase order</Button>}
                                </div>
                            </Card>
                        )}

                        {approval && <ApprovalPanel entity="purchase-requests" id={pr.id} approval={approval} />}
                        <AttachmentsPanel entity="purchase-requests" id={pr.id} attachments={attachments} canManage={can.manage} />
                        {pr.quotations.length > 0 && <LinkedList title="Quotations" base="quotations" docs={pr.quotations} />}
                        {pr.purchase_orders.length > 0 && <LinkedList title="Purchase Orders" base="purchase-orders" docs={pr.purchase_orders} />}
                    </div>
                </div>
            </div>
            <SendDocumentModal open={sendOpen} onClose={() => setSendOpen(false)} meta={send} kindLabel="purchase request" />
        </ProcurementLayout>
    );
}

function Field({ label, value }: { label: string; value: string | null }) {
    return <div><dt className="text-xs uppercase tracking-wide text-muted-foreground/70">{label}</dt><dd className="mt-0.5 text-foreground">{value ?? '—'}</dd></div>;
}
function Row({ label, value }: { label: string; value: string }) {
    return <div className="flex items-center justify-between"><span className="text-muted-foreground">{label}</span><span className="text-foreground">{value}</span></div>;
}
function LinkedList({ title, base, docs }: { title: string; base: string; docs: LinkedDoc[] }) {
    return (
        <Card className="p-5">
            <h2 className="mb-2 text-xs font-bold uppercase tracking-wider text-muted-foreground/70">{title}</h2>
            <div className="space-y-2">
                {docs.map(d => (
                    <Link key={d.id} href={`/procurement/${base}/${d.id}`} className="flex items-center justify-between rounded-lg border border-border p-2.5 text-sm hover:bg-secondary/50">
                        <span className="font-mono text-foreground">{d.number}</span>
                        <span className="flex items-center gap-2"><Pill color={d.status_color} label={d.status_label} /><span className="text-muted-foreground">{formatCurrency(d.total, d.currency)}</span></span>
                    </Link>
                ))}
            </div>
        </Card>
    );
}
