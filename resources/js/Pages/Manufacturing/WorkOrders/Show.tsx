import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { ManufacturingLayout } from '@/Components/layout/ManufacturingLayout';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { Pill } from '@/Components/ui/Pill';
import { ConfirmDialog } from '@/Components/ui/Modal';
import { CompleteWorkOrderModal } from '@/Components/manufacturing/CompleteWorkOrderModal';
import { formatCurrency, cn } from '@/Lib/utils';
import { ArrowLeft, Factory, Trash2, Send, Play, Hammer, Ban, Package, ListTree } from 'lucide-react';

interface Requirement { product_id: number; sku: string; name: string; unit_of_measure: string; quantity_per: number; required: number; available: number; sufficient: boolean }
interface Order {
    id: number; number: string; status: string; status_label: string; status_color: string; can_complete: boolean;
    product: { id: number | null; sku: string | null; name: string | null; unit_of_measure: string | null };
    warehouse: { id: number | null; name: string | null };
    bom: { id: number; name: string; version: string } | null;
    quantity_planned: number; quantity_produced: number; build_cost: number;
    scheduled_date: string | null; completed_at: string | null; notes: string | null;
}

interface Props {
    order: Order;
    requirements: Requirement[];
    can: { manage: boolean; complete: boolean };
}

export default function WorkOrderShow({ order, requirements, can }: Props) {
    const [buildOpen, setBuildOpen] = useState(false);
    const [deleting, setDeleting] = useState(false);
    const [cancelling, setCancelling] = useState(false);
    const [processing, setProcessing] = useState(false);

    const act = (verb: string) => {
        setProcessing(true);
        router.post(`/manufacturing/work-orders/${order.id}/${verb}`, {}, { preserveScroll: true, onFinish: () => setProcessing(false) });
    };
    const confirmDelete = () => {
        setProcessing(true);
        router.delete(`/manufacturing/work-orders/${order.id}`, { onFinish: () => setProcessing(false) });
    };

    const isTerminal = ['completed', 'cancelled'].includes(order.status);
    const anyShortage = requirements.some(r => !r.sufficient);

    return (
        <ManufacturingLayout>
            <Head title={`${order.number} · Manufacturing`} />
            <div className="mx-auto max-w-4xl px-4 py-6 sm:px-6">
                <Link href="/manufacturing/work-orders" className="mb-4 inline-flex items-center gap-1.5 text-sm font-medium text-muted-foreground hover:text-foreground">
                    <ArrowLeft className="h-4 w-4" /> Work Orders
                </Link>

                <div className="card-surface mb-6 p-6">
                    <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div className="flex items-center gap-4">
                            <div className="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-brand-gradient text-white"><Factory className="h-7 w-7" /></div>
                            <div>
                                <h1 className="font-mono text-2xl font-bold tracking-tight text-foreground">{order.number}</h1>
                                <div className="mt-1 flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                                    <Pill color={order.status_color} label={order.status_label} />
                                    {order.product.id && <Link href={`/inventory/products/${order.product.id}`} className="inline-flex items-center gap-1 text-primary hover:underline"><Package className="h-3.5 w-3.5" />{order.product.sku} · {order.product.name}</Link>}
                                    {order.warehouse.name && <span className="chip">→ {order.warehouse.name}</span>}
                                </div>
                            </div>
                        </div>
                        <div className="flex flex-wrap items-center gap-2">
                            {order.status === 'draft' && can.manage && <Button variant="secondary" size="sm" icon={Send} onClick={() => act('release')} disabled={processing}>Release</Button>}
                            {order.status === 'released' && can.manage && <Button variant="secondary" size="sm" icon={Play} onClick={() => act('start')} disabled={processing}>Start</Button>}
                            {order.can_complete && can.complete && <Button variant="success" size="sm" icon={Hammer} onClick={() => setBuildOpen(true)} disabled={processing}>Build</Button>}
                            {!isTerminal && can.manage && <Button variant="ghost" size="sm" icon={Ban} onClick={() => setCancelling(true)} disabled={processing}>Cancel</Button>}
                            {can.manage && order.quantity_produced === 0 && <Button variant="ghost" size="sm" icon={Trash2} onClick={() => setDeleting(true)} disabled={processing}>Delete</Button>}
                        </div>
                    </div>

                    <div className="mt-5 grid grid-cols-2 gap-4 border-t border-border pt-4 sm:grid-cols-4">
                        <div><p className="text-xs text-muted-foreground">Planned</p><p className="mt-0.5 text-lg font-bold text-foreground">{order.quantity_planned} {order.product.unit_of_measure}</p></div>
                        <div><p className="text-xs text-muted-foreground">Produced</p><p className="mt-0.5 text-lg font-bold text-foreground">{order.quantity_produced}</p></div>
                        <div><p className="text-xs text-muted-foreground">Build cost</p><p className="mt-0.5 text-lg font-bold text-foreground">{formatCurrency(order.build_cost)}</p></div>
                        <div><p className="text-xs text-muted-foreground">BOM</p><p className="mt-0.5 text-sm font-medium text-foreground">{order.bom ? `${order.bom.name} (${order.bom.version})` : '— none —'}</p></div>
                    </div>
                    {order.notes && <p className="mt-4 whitespace-pre-line border-t border-border pt-4 text-sm text-muted-foreground">{order.notes}</p>}
                </div>

                <Card className="overflow-hidden">
                    <div className="flex items-center justify-between border-b border-border px-5 py-3">
                        <h2 className="flex items-center gap-2 text-sm font-bold uppercase tracking-wider text-muted-foreground/70"><ListTree className="h-4 w-4" /> Component requirements</h2>
                        {anyShortage && order.can_complete && <span className="text-xs font-medium text-red-600">Some components are short</span>}
                    </div>
                    {requirements.length === 0 ? (
                        <p className="px-5 py-4 text-sm text-muted-foreground">{order.bom ? 'This BOM has no components.' : 'No BOM linked — add one to enable building.'}</p>
                    ) : (
                        <table className="w-full text-sm">
                            <thead className="bg-secondary/40 text-left text-xs uppercase text-muted-foreground/70">
                                <tr><th className="px-5 py-2">Component</th><th className="px-5 py-2 text-right">Required</th><th className="px-5 py-2 text-right">On hand</th><th className="px-5 py-2 text-right">Status</th></tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {requirements.map(r => (
                                    <tr key={r.product_id}>
                                        <td className="px-5 py-2.5">
                                            <Link href={`/inventory/products/${r.product_id}`} className="font-medium text-foreground hover:text-primary">{r.name}</Link>
                                            <span className="block font-mono text-xs text-muted-foreground">{r.sku}</span>
                                        </td>
                                        <td className="px-5 py-2.5 text-right text-foreground">{r.required} {r.unit_of_measure}</td>
                                        <td className="px-5 py-2.5 text-right text-muted-foreground">{r.available}</td>
                                        <td className="px-5 py-2.5 text-right">
                                            <Pill color={r.sufficient ? 'green' : 'red'} label={r.sufficient ? 'OK' : 'Short'} />
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                </Card>
            </div>

            {buildOpen && (
                <CompleteWorkOrderModal
                    open
                    onClose={() => setBuildOpen(false)}
                    orderId={order.id}
                    number={order.number}
                    defaultQuantity={order.quantity_planned - order.quantity_produced}
                    requirements={requirements}
                />
            )}
            <ConfirmDialog open={cancelling} onClose={() => setCancelling(false)} onConfirm={() => { setCancelling(false); act('cancel'); }}
                confirmLabel="Cancel WO" title="Cancel work order?" message={<>Cancel <span className="font-mono font-medium text-foreground">{order.number}</span>?</>} />
            <ConfirmDialog open={deleting} onClose={() => setDeleting(false)} onConfirm={confirmDelete} processing={processing}
                title="Delete work order?" message={<>This permanently removes <span className="font-mono font-medium text-foreground">{order.number}</span>.</>} />
        </ManufacturingLayout>
    );
}
