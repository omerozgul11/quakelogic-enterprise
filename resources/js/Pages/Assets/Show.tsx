import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { AssetLayout } from '@/Components/layout/AssetLayout';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { Pill } from '@/Components/ui/Pill';
import { Select } from '@/Components/ui/Select';
import { ConfirmDialog } from '@/Components/ui/Modal';
import { AssetFormModal, EditableAsset } from '@/Components/assets/AssetFormModal';
import { MaintenanceModal } from '@/Components/assets/MaintenanceModal';
import { formatCurrency, cn } from '@/Lib/utils';
import { ArrowLeft, Cpu, Pencil, Trash2, Plus, Wrench, Package, ShieldCheck, MapPin } from 'lucide-react';

interface MaintRow { id: number; type: string; type_label: string; type_color: string; status: string; description: string; cost: number | null; performed_at: string | null; next_due_at: string | null; by: string | null; notes: string | null }
interface Asset extends EditableAsset {
    id: number; asset_tag: string; name: string; status: string; status_label: string; status_color: string;
    warranty_active: boolean; retired_at: string | null;
    product: { id: number; sku: string; name: string } | null;
    assignee: string | null; company: string | null;
}
interface FormData { products: { id: number; sku: string; name: string }[]; companies: { id: number; name: string }[]; users: { id: number; name: string }[] }

interface Props {
    asset: Asset;
    maintenance: MaintRow[];
    statuses: { value: string; label: string }[];
    maintenance_types: { value: string; label: string }[];
    form: FormData;
    can: { manage: boolean; maintain: boolean };
}

export default function AssetShow({ asset, maintenance, statuses, maintenance_types, form, can }: Props) {
    const [editOpen, setEditOpen] = useState(false);
    const [maintOpen, setMaintOpen] = useState(false);
    const [deleting, setDeleting] = useState(false);
    const [processing, setProcessing] = useState(false);

    const transition = (status: string) => {
        if (status === asset.status) return;
        router.post(`/assets/registry/${asset.id}/transition`, { status }, { preserveScroll: true });
    };
    const confirmDelete = () => {
        setProcessing(true);
        router.delete(`/assets/registry/${asset.id}`, { onFinish: () => setProcessing(false) });
    };
    const deleteMaint = (id: number) => router.delete(`/assets/registry/${asset.id}/maintenance/${id}`, { preserveScroll: true });

    const details: Array<{ label: string; value: React.ReactNode }> = [
        { label: 'Serial number', value: asset.serial_number || '—' },
        { label: 'Category', value: asset.category || '—' },
        { label: 'Condition', value: asset.condition ? <span className="capitalize">{asset.condition}</span> : '—' },
        { label: 'Product type', value: asset.product ? <Link href={`/inventory/products/${asset.product.id}`} className="text-primary hover:underline">{asset.product.sku}</Link> : '—' },
        { label: 'Assigned to', value: asset.assignee || '—' },
        { label: 'Deployed at', value: asset.company || (asset.location ? '' : '— internal —') },
        { label: 'Purchased', value: asset.purchased_at || '—' },
        { label: 'Warranty', value: asset.warranty_expires_at ? <span className={asset.warranty_active ? 'text-emerald-600' : 'text-muted-foreground'}>{asset.warranty_expires_at}{asset.warranty_active ? ' (active)' : ' (expired)'}</span> : '—' },
        { label: 'Purchase cost', value: asset.purchase_cost != null ? formatCurrency(asset.purchase_cost, asset.currency) : '—' },
        { label: 'Current value', value: asset.current_value != null ? formatCurrency(asset.current_value, asset.currency) : '—' },
    ];

    return (
        <AssetLayout>
            <Head title={`${asset.asset_tag} · Assets`} />
            <div className="mx-auto max-w-5xl px-4 py-6 sm:px-6">
                <Link href="/assets/registry" className="mb-4 inline-flex items-center gap-1.5 text-sm font-medium text-muted-foreground hover:text-foreground">
                    <ArrowLeft className="h-4 w-4" /> Assets
                </Link>

                <div className="card-surface mb-6 p-6">
                    <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div className="flex items-center gap-4">
                            <div className="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-500 to-violet-500 text-white"><Cpu className="h-7 w-7" /></div>
                            <div>
                                <h1 className="text-2xl font-bold tracking-tight text-foreground">{asset.name}</h1>
                                <div className="mt-1 flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                                    <span className="font-mono text-xs">{asset.asset_tag}</span>
                                    <Pill color={asset.status_color} label={asset.status_label} />
                                    {asset.warranty_active && <span className="inline-flex items-center gap-1 text-xs text-emerald-600"><ShieldCheck className="h-3.5 w-3.5" /> Warranty</span>}
                                </div>
                                {asset.location && <p className="mt-1 inline-flex items-center gap-1 text-sm text-muted-foreground"><MapPin className="h-3.5 w-3.5" /> {asset.location}</p>}
                            </div>
                        </div>
                        <div className="flex flex-wrap items-center gap-2">
                            {can.manage && (
                                <Select className="w-44" value={asset.status} onChange={transition} options={statuses} />
                            )}
                            {can.manage && <Button variant="secondary" size="sm" icon={Pencil} onClick={() => setEditOpen(true)}>Edit</Button>}
                            {can.manage && <Button variant="danger" size="sm" icon={Trash2} onClick={() => setDeleting(true)}>Delete</Button>}
                        </div>
                    </div>
                    {asset.notes && <p className="mt-4 whitespace-pre-line border-t border-border pt-4 text-sm text-muted-foreground">{asset.notes}</p>}
                </div>

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-5">
                    <Card className="p-5 lg:col-span-2">
                        <h2 className="mb-3 flex items-center gap-2 text-sm font-bold uppercase tracking-wider text-muted-foreground/70"><Package className="h-4 w-4" /> Details</h2>
                        <dl className="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                            {details.map(d => (
                                <div key={d.label}><dt className="text-xs text-muted-foreground">{d.label}</dt><dd className="text-foreground">{d.value}</dd></div>
                            ))}
                        </dl>
                    </Card>

                    <Card className="p-5 lg:col-span-3">
                        <div className="mb-3 flex items-center justify-between">
                            <h2 className="flex items-center gap-2 text-sm font-bold uppercase tracking-wider text-muted-foreground/70"><Wrench className="h-4 w-4" /> Maintenance history</h2>
                            {can.maintain && <button onClick={() => setMaintOpen(true)} className="inline-flex items-center gap-1 text-sm font-medium text-primary hover:underline"><Plus className="h-3.5 w-3.5" /> Log</button>}
                        </div>
                        {maintenance.length === 0 ? (
                            <p className="py-4 text-sm text-muted-foreground">No maintenance records yet.</p>
                        ) : (
                            <div className="space-y-2">
                                {maintenance.map(m => (
                                    <div key={m.id} className="rounded-lg border border-border p-3">
                                        <div className="flex items-center justify-between gap-2">
                                            <span className="flex items-center gap-2">
                                                <Pill color={m.type_color} label={m.type_label} />
                                                <span className="text-xs text-muted-foreground">{m.performed_at}</span>
                                                {m.status !== 'completed' && <span className="chip capitalize">{m.status.replace('_', ' ')}</span>}
                                            </span>
                                            <span className="flex items-center gap-2">
                                                {m.cost != null && <span className="text-sm font-medium text-foreground">{formatCurrency(m.cost)}</span>}
                                                {can.maintain && <button onClick={() => deleteMaint(m.id)} className="rounded-md p-1 text-muted-foreground hover:bg-destructive/10 hover:text-destructive"><Trash2 className="h-3.5 w-3.5" /></button>}
                                            </span>
                                        </div>
                                        <p className="mt-1.5 text-sm text-foreground">{m.description}</p>
                                        <div className="mt-1 flex flex-wrap gap-x-3 text-xs text-muted-foreground">
                                            {m.next_due_at && <span>Next due {m.next_due_at}</span>}
                                            {m.by && <span>by {m.by}</span>}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </Card>
                </div>
            </div>

            {editOpen && <AssetFormModal open onClose={() => setEditOpen(false)} asset={asset} statuses={statuses} form={form} />}
            {maintOpen && <MaintenanceModal open onClose={() => setMaintOpen(false)} assetId={asset.id} types={maintenance_types} />}
            <ConfirmDialog open={deleting} onClose={() => setDeleting(false)} onConfirm={confirmDelete} processing={processing}
                title="Delete asset?" message={<>This soft-deletes <span className="font-mono font-medium text-foreground">{asset.asset_tag}</span>.</>} />
        </AssetLayout>
    );
}
