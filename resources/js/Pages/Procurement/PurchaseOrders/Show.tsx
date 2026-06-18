import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { ProcurementLayout } from '@/Components/layout/ProcurementLayout';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { Pill } from '@/Components/ui/Pill';
import { ConfirmDialog } from '@/Components/ui/Modal';
import { ReceiveModal } from '@/Components/procurement/ReceiveModal';
import { formatCurrency, cn } from '@/Lib/utils';
import { ArrowLeft, ShoppingCart, Trash2, Send, BadgeCheck, Truck, Ban, PackageCheck } from 'lucide-react';

interface Item {
    id: number; description: string; sku: string | null; product_id: number | null; product: string | null;
    quantity_ordered: number; quantity_received: number; outstanding: number; unit_cost: number; line_total: number;
}
interface Order {
    id: number; number: string; status: string; status_label: string; status_color: string;
    is_editable: boolean; can_receive: boolean;
    supplier: { id: number | null; name: string | null; code: string | null; payment_terms: string | null };
    warehouse: { id: number; name: string } | null;
    order_date: string | null; expected_date: string | null; currency: string;
    subtotal: number; tax_rate: number; tax_amount: number; shipping_amount: number; total: number;
    notes: string | null; approved_by: string | null; approved_at: string | null;
    items: Item[];
}

interface Props {
    order: Order;
    can: { manage: boolean; approve: boolean; receive: boolean };
}

export default function PurchaseOrderShow({ order, can }: Props) {
    const [receiveOpen, setReceiveOpen] = useState(false);
    const [deleting, setDeleting] = useState(false);
    const [cancelling, setCancelling] = useState(false);
    const [processing, setProcessing] = useState(false);

    const act = (verb: string) => {
        setProcessing(true);
        router.post(`/procurement/purchase-orders/${order.id}/${verb}`, {}, { preserveScroll: true, onFinish: () => setProcessing(false) });
    };
    const confirmDelete = () => {
        setProcessing(true);
        router.delete(`/procurement/purchase-orders/${order.id}`, { onFinish: () => setProcessing(false) });
    };

    const received = order.items.reduce((s, i) => s + i.quantity_received, 0);
    const ordered = order.items.reduce((s, i) => s + i.quantity_ordered, 0);
    const pct = ordered > 0 ? Math.round((received / ordered) * 100) : 0;
    const hasReceipts = received > 0;
    const isTerminal = ['received', 'closed', 'cancelled'].includes(order.status);

    return (
        <ProcurementLayout>
            <Head title={`${order.number} · Procurement`} />
            <div className="mx-auto max-w-5xl px-4 py-6 sm:px-6">
                <Link href="/procurement/purchase-orders" className="mb-4 inline-flex items-center gap-1.5 text-sm font-medium text-muted-foreground hover:text-foreground">
                    <ArrowLeft className="h-4 w-4" /> Purchase Orders
                </Link>

                <div className="card-surface mb-6 p-6">
                    <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div className="flex items-center gap-4">
                            <div className="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-brand-gradient text-white"><ShoppingCart className="h-7 w-7" /></div>
                            <div>
                                <h1 className="font-mono text-2xl font-bold tracking-tight text-foreground">{order.number}</h1>
                                <div className="mt-1 flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                                    <Pill color={order.status_color} label={order.status_label} />
                                    {order.supplier.id
                                        ? <Link href={`/procurement/suppliers/${order.supplier.id}`} className="text-primary hover:underline">{order.supplier.name}</Link>
                                        : <span>{order.supplier.name}</span>}
                                    {order.warehouse && <span className="chip">→ {order.warehouse.name}</span>}
                                </div>
                            </div>
                        </div>
                        <div className="flex flex-wrap items-center gap-2">
                            {order.status === 'draft' && can.manage && <Button variant="secondary" size="sm" icon={Send} onClick={() => act('submit')} disabled={processing}>Submit</Button>}
                            {(order.status === 'pending_approval' || order.status === 'draft') && can.approve && <Button variant="secondary" size="sm" icon={BadgeCheck} onClick={() => act('approve')} disabled={processing}>Approve</Button>}
                            {order.status === 'approved' && can.manage && <Button variant="secondary" size="sm" icon={Truck} onClick={() => act('sent')} disabled={processing}>Mark sent</Button>}
                            {order.can_receive && can.receive && <Button variant="success" size="sm" icon={PackageCheck} onClick={() => setReceiveOpen(true)} disabled={processing}>Receive</Button>}
                            {!isTerminal && can.manage && <Button variant="ghost" size="sm" icon={Ban} onClick={() => setCancelling(true)} disabled={processing}>Cancel</Button>}
                            {can.manage && !hasReceipts && <Button variant="ghost" size="sm" icon={Trash2} onClick={() => setDeleting(true)} disabled={processing}>Delete</Button>}
                        </div>
                    </div>

                    <div className="mt-5 grid grid-cols-2 gap-4 border-t border-border pt-4 sm:grid-cols-4">
                        <div><p className="text-xs text-muted-foreground">Order date</p><p className="mt-0.5 font-medium text-foreground">{order.order_date ?? '—'}</p></div>
                        <div><p className="text-xs text-muted-foreground">Expected</p><p className="mt-0.5 font-medium text-foreground">{order.expected_date ?? '—'}</p></div>
                        <div><p className="text-xs text-muted-foreground">Terms</p><p className="mt-0.5 font-medium text-foreground">{order.supplier.payment_terms ?? '—'}</p></div>
                        <div><p className="text-xs text-muted-foreground">Approved by</p><p className="mt-0.5 font-medium text-foreground">{order.approved_by ?? '—'}</p></div>
                    </div>

                    {hasReceipts && (
                        <div className="mt-4 border-t border-border pt-4">
                            <div className="mb-1 flex items-center justify-between text-xs text-muted-foreground"><span>Received</span><span>{pct}%</span></div>
                            <div className="h-2 overflow-hidden rounded-full bg-secondary"><div className="h-full rounded-full bg-emerald-500" style={{ width: `${pct}%` }} /></div>
                        </div>
                    )}
                </div>

                <Card className="mb-4 overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="border-b border-border bg-secondary/40 text-left text-xs uppercase text-muted-foreground/70">
                                <tr>
                                    <th className="px-4 py-2.5">Item</th>
                                    <th className="px-4 py-2.5 text-right">Ordered</th>
                                    <th className="px-4 py-2.5 text-right">Received</th>
                                    <th className="px-4 py-2.5 text-right">Unit cost</th>
                                    <th className="px-4 py-2.5 text-right">Line total</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {order.items.map(i => (
                                    <tr key={i.id}>
                                        <td className="px-4 py-2.5">
                                            {i.product_id
                                                ? <Link href={`/inventory/products/${i.product_id}`} className="font-medium text-foreground hover:text-primary">{i.description}</Link>
                                                : <span className="font-medium text-foreground">{i.description}</span>}
                                            {i.sku && <span className="block font-mono text-xs text-muted-foreground">{i.sku}</span>}
                                        </td>
                                        <td className="px-4 py-2.5 text-right text-foreground">{i.quantity_ordered}</td>
                                        <td className="px-4 py-2.5 text-right">
                                            <span className={cn(i.quantity_received >= i.quantity_ordered ? 'text-emerald-600' : i.quantity_received > 0 ? 'text-amber-600' : 'text-muted-foreground')}>
                                                {i.quantity_received}
                                            </span>
                                        </td>
                                        <td className="px-4 py-2.5 text-right text-muted-foreground">{formatCurrency(i.unit_cost, order.currency)}</td>
                                        <td className="px-4 py-2.5 text-right font-medium text-foreground">{formatCurrency(i.line_total, order.currency)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <div className="flex justify-end border-t border-border px-4 py-4">
                        <div className="w-full max-w-xs space-y-1.5 text-sm">
                            <div className="flex justify-between"><span className="text-muted-foreground">Subtotal</span><span className="text-foreground">{formatCurrency(order.subtotal, order.currency)}</span></div>
                            <div className="flex justify-between"><span className="text-muted-foreground">Tax ({order.tax_rate}%)</span><span className="text-foreground">{formatCurrency(order.tax_amount, order.currency)}</span></div>
                            <div className="flex justify-between"><span className="text-muted-foreground">Shipping</span><span className="text-foreground">{formatCurrency(order.shipping_amount, order.currency)}</span></div>
                            <div className="flex justify-between border-t border-border pt-1.5 text-base font-bold text-foreground"><span>Total</span><span>{formatCurrency(order.total, order.currency)}</span></div>
                        </div>
                    </div>
                </Card>

                {order.notes && (
                    <Card className="p-5">
                        <h2 className="mb-2 text-sm font-bold uppercase tracking-wider text-muted-foreground/70">Notes</h2>
                        <p className="whitespace-pre-line text-sm text-muted-foreground">{order.notes}</p>
                    </Card>
                )}
            </div>

            {receiveOpen && <ReceiveModal open onClose={() => setReceiveOpen(false)} orderId={order.id} items={order.items} />}
            <ConfirmDialog open={cancelling} onClose={() => setCancelling(false)} onConfirm={() => { setCancelling(false); act('cancel'); }}
                confirmLabel="Cancel PO" title="Cancel purchase order?" message={<>Cancel <span className="font-mono font-medium text-foreground">{order.number}</span>? This cannot be undone.</>} />
            <ConfirmDialog open={deleting} onClose={() => setDeleting(false)} onConfirm={confirmDelete} processing={processing}
                title="Delete purchase order?" message={<>This permanently removes <span className="font-mono font-medium text-foreground">{order.number}</span>.</>} />
        </ProcurementLayout>
    );
}
