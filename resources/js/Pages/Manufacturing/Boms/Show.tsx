import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { ManufacturingLayout } from '@/Components/layout/ManufacturingLayout';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { Pill } from '@/Components/ui/Pill';
import { ConfirmDialog } from '@/Components/ui/Modal';
import { formatCurrency } from '@/Lib/utils';
import { ArrowLeft, ListTree, Pencil, Trash2, Hammer, Package } from 'lucide-react';

interface Item { id: number; product_id: number; sku: string | null; name: string | null; unit_of_measure: string | null; quantity_per: number; unit_cost: number; notes: string | null }
interface Bom {
    id: number; name: string; version: string; status_label: string; status_color: string;
    output_quantity: number; is_default: boolean; notes: string | null;
    product: { id: number | null; sku: string | null; name: string | null };
    est_unit_cost: number; items: Item[];
}

interface Props {
    bom: Bom;
    can: { manage: boolean; build: boolean };
}

export default function BomShow({ bom, can }: Props) {
    const [deleting, setDeleting] = useState(false);
    const [processing, setProcessing] = useState(false);

    const confirmDelete = () => {
        setProcessing(true);
        router.delete(`/manufacturing/boms/${bom.id}`, { onFinish: () => setProcessing(false) });
    };

    return (
        <ManufacturingLayout>
            <Head title={`${bom.name} · Manufacturing`} />
            <div className="mx-auto max-w-4xl px-4 py-6 sm:px-6">
                <Link href="/manufacturing/boms" className="mb-4 inline-flex items-center gap-1.5 text-sm font-medium text-muted-foreground hover:text-foreground">
                    <ArrowLeft className="h-4 w-4" /> BOMs
                </Link>

                <div className="card-surface mb-6 p-6">
                    <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div className="flex items-center gap-4">
                            <div className="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-500 to-violet-500 text-white"><ListTree className="h-7 w-7" /></div>
                            <div>
                                <h1 className="text-2xl font-bold tracking-tight text-foreground">{bom.name}</h1>
                                <div className="mt-1 flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                                    <span className="font-mono text-xs">{bom.version}</span>
                                    <Pill color={bom.status_color} label={bom.status_label} />
                                    {bom.is_default && <Pill color="blue" label="Default" />}
                                </div>
                                {bom.product.id && (
                                    <p className="mt-1 inline-flex items-center gap-1.5 text-sm">
                                        <Package className="h-3.5 w-3.5 text-muted-foreground" />
                                        Builds <Link href={`/inventory/products/${bom.product.id}`} className="font-medium text-primary hover:underline">{bom.product.sku} · {bom.product.name}</Link>
                                    </p>
                                )}
                            </div>
                        </div>
                        <div className="flex items-center gap-2">
                            {can.build && <Button variant="secondary" size="sm" icon={Hammer} href={`/manufacturing/work-orders/create?bom=${bom.id}`}>New work order</Button>}
                            {can.manage && <Button variant="secondary" size="sm" icon={Pencil} href={`/manufacturing/boms/${bom.id}/edit`}>Edit</Button>}
                            {can.manage && <Button variant="danger" size="sm" icon={Trash2} onClick={() => setDeleting(true)}>Delete</Button>}
                        </div>
                    </div>
                    <div className="mt-5 grid grid-cols-3 gap-4 border-t border-border pt-4">
                        <div><p className="text-xs text-muted-foreground">Yields</p><p className="mt-0.5 text-lg font-bold text-foreground">{bom.output_quantity}</p></div>
                        <div><p className="text-xs text-muted-foreground">Components</p><p className="mt-0.5 text-lg font-bold text-foreground">{bom.items.length}</p></div>
                        <div><p className="text-xs text-muted-foreground">Est. unit cost</p><p className="mt-0.5 text-lg font-bold text-foreground">{formatCurrency(bom.est_unit_cost)}</p></div>
                    </div>
                    {bom.notes && <p className="mt-4 whitespace-pre-line border-t border-border pt-4 text-sm text-muted-foreground">{bom.notes}</p>}
                </div>

                <Card className="overflow-hidden">
                    <div className="border-b border-border px-5 py-3"><h2 className="text-sm font-bold uppercase tracking-wider text-muted-foreground/70">Components</h2></div>
                    <table className="w-full text-sm">
                        <thead className="bg-secondary/40 text-left text-xs uppercase text-muted-foreground/70">
                            <tr><th className="px-5 py-2">Component</th><th className="px-5 py-2 text-right">Qty / batch</th><th className="px-5 py-2 text-right">Unit cost</th><th className="px-5 py-2 text-right">Ext. cost</th></tr>
                        </thead>
                        <tbody className="divide-y divide-border">
                            {bom.items.map(i => (
                                <tr key={i.id}>
                                    <td className="px-5 py-2.5">
                                        <Link href={`/inventory/products/${i.product_id}`} className="font-medium text-foreground hover:text-primary">{i.name}</Link>
                                        <span className="block font-mono text-xs text-muted-foreground">{i.sku}</span>
                                    </td>
                                    <td className="px-5 py-2.5 text-right text-foreground">{i.quantity_per} {i.unit_of_measure}</td>
                                    <td className="px-5 py-2.5 text-right text-muted-foreground">{formatCurrency(i.unit_cost)}</td>
                                    <td className="px-5 py-2.5 text-right font-medium text-foreground">{formatCurrency(i.quantity_per * i.unit_cost)}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </Card>
            </div>

            <ConfirmDialog open={deleting} onClose={() => setDeleting(false)} onConfirm={confirmDelete} processing={processing}
                title="Delete BOM?" message={<>This soft-deletes <span className="font-medium text-foreground">{bom.name}</span>.</>} />
        </ManufacturingLayout>
    );
}
