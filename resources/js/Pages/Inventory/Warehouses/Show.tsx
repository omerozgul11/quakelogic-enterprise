import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { InventoryLayout } from '@/Components/layout/InventoryLayout';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { Pill } from '@/Components/ui/Pill';
import { ConfirmDialog } from '@/Components/ui/Modal';
import { WarehouseFormModal } from '@/Components/inventory/WarehouseFormModal';
import { LocationFormModal, EditableLocation } from '@/Components/inventory/LocationFormModal';
import { formatCurrency } from '@/Lib/utils';
import { ArrowLeft, Warehouse as WarehouseIcon, Pencil, Trash2, Plus, MapPin } from 'lucide-react';

interface Warehouse {
    id: number; code: string; name: string; type: string;
    address_line1: string | null; city: string | null; state: string | null;
    postal_code: string | null; country: string | null;
    is_default: boolean; is_active: boolean; notes: string | null; value: number;
}
interface LocationRow extends EditableLocation { id: number; code: string }
interface StockRow { product_id: number; sku: string | null; name: string | null; unit_of_measure: string | null; on_hand: number; average_cost: number; value: number }

interface Props {
    warehouse: Warehouse;
    locations: LocationRow[];
    stock: StockRow[];
    can: { manage: boolean };
}

export default function WarehouseShow({ warehouse, locations, stock, can }: Props) {
    const [editOpen, setEditOpen] = useState(false);
    const [deleting, setDeleting] = useState(false);
    const [processing, setProcessing] = useState(false);
    const [locOpen, setLocOpen] = useState(false);
    const [editLoc, setEditLoc] = useState<LocationRow | null>(null);
    const [delLoc, setDelLoc] = useState<LocationRow | null>(null);

    const confirmDelete = () => {
        setProcessing(true);
        router.delete(`/inventory/warehouses/${warehouse.id}`, { onFinish: () => setProcessing(false) });
    };
    const openAddLoc = () => { setEditLoc(null); setLocOpen(true); };
    const openEditLoc = (l: LocationRow) => { setEditLoc(l); setLocOpen(true); };
    const confirmDelLoc = () => {
        if (!delLoc) return;
        router.delete(`/inventory/warehouses/${warehouse.id}/locations/${delLoc.id}`, { preserveScroll: true, onFinish: () => setDelLoc(null) });
    };

    return (
        <InventoryLayout>
            <Head title={`${warehouse.name} · Inventory`} />
            <div className="mx-auto max-w-6xl px-4 py-6 sm:px-6">
                <Link href="/inventory/warehouses" className="mb-4 inline-flex items-center gap-1.5 text-sm font-medium text-muted-foreground hover:text-foreground">
                    <ArrowLeft className="h-4 w-4" /> Warehouses
                </Link>

                <div className="card-surface mb-6 p-6">
                    <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div className="flex items-center gap-4">
                            <div className="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-sky-500 to-blue-500 text-white">
                                <WarehouseIcon className="h-7 w-7" />
                            </div>
                            <div>
                                <h1 className="text-2xl font-bold tracking-tight text-foreground">{warehouse.name}</h1>
                                <div className="mt-1 flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                                    <span className="font-mono text-xs">{warehouse.code}</span>
                                    <span className="capitalize">{warehouse.type}</span>
                                    {warehouse.is_default && <Pill color="blue" label="Default" />}
                                    {!warehouse.is_active && <span className="chip">Inactive</span>}
                                </div>
                                {(warehouse.address_line1 || warehouse.city) && (
                                    <p className="mt-1 inline-flex items-center gap-1 text-sm text-muted-foreground">
                                        <MapPin className="h-3.5 w-3.5" />
                                        {[warehouse.address_line1, warehouse.city, warehouse.state, warehouse.postal_code].filter(Boolean).join(', ')}
                                    </p>
                                )}
                            </div>
                        </div>
                        {can.manage && (
                            <div className="flex items-center gap-2">
                                <Button variant="secondary" icon={Pencil} onClick={() => setEditOpen(true)}>Edit</Button>
                                <Button variant="danger" icon={Trash2} onClick={() => setDeleting(true)}>Delete</Button>
                            </div>
                        )}
                    </div>
                    <div className="mt-5 grid grid-cols-3 gap-4 border-t border-border pt-4">
                        <div><p className="text-xs text-muted-foreground">Stock value</p><p className="mt-0.5 text-lg font-bold text-foreground">{formatCurrency(warehouse.value)}</p></div>
                        <div><p className="text-xs text-muted-foreground">SKUs in stock</p><p className="mt-0.5 text-lg font-bold text-foreground">{stock.length}</p></div>
                        <div><p className="text-xs text-muted-foreground">Locations</p><p className="mt-0.5 text-lg font-bold text-foreground">{locations.length}</p></div>
                    </div>
                    {warehouse.notes && <p className="mt-4 whitespace-pre-line border-t border-border pt-4 text-sm text-muted-foreground">{warehouse.notes}</p>}
                </div>

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <Card className="p-5">
                        <div className="mb-3 flex items-center justify-between">
                            <h2 className="text-sm font-bold uppercase tracking-wider text-muted-foreground/70">Locations / bins</h2>
                            {can.manage && <button onClick={openAddLoc} className="inline-flex items-center gap-1 text-sm font-medium text-primary hover:underline"><Plus className="h-3.5 w-3.5" /> Add</button>}
                        </div>
                        {locations.length === 0 ? (
                            <p className="py-4 text-sm text-muted-foreground">No bins defined.</p>
                        ) : (
                            <div className="space-y-1.5">
                                {locations.map(l => (
                                    <div key={l.id} className="flex items-center gap-3 rounded-lg border border-border px-3 py-2">
                                        <span className="min-w-0 flex-1">
                                            <span className="block truncate font-mono text-sm font-medium text-foreground">{l.code}</span>
                                            {l.name && <span className="block truncate text-xs text-muted-foreground">{l.name}</span>}
                                        </span>
                                        <Pill color="gray" label={l.type ?? 'bin'} />
                                        {can.manage && (
                                            <span className="flex items-center gap-1">
                                                <button onClick={() => openEditLoc(l)} className="rounded-md p-1.5 text-muted-foreground hover:bg-secondary hover:text-foreground" title="Edit"><Pencil className="h-3.5 w-3.5" /></button>
                                                <button onClick={() => setDelLoc(l)} className="rounded-md p-1.5 text-muted-foreground hover:bg-destructive/10 hover:text-destructive" title="Remove"><Trash2 className="h-3.5 w-3.5" /></button>
                                            </span>
                                        )}
                                    </div>
                                ))}
                            </div>
                        )}
                    </Card>

                    <Card className="p-5">
                        <h2 className="mb-3 text-sm font-bold uppercase tracking-wider text-muted-foreground/70">Stock on hand</h2>
                        {stock.length === 0 ? (
                            <p className="py-4 text-sm text-muted-foreground">No stock in this warehouse.</p>
                        ) : (
                            <table className="w-full text-sm">
                                <thead><tr className="text-left text-xs uppercase text-muted-foreground/70">
                                    <th className="pb-2">Product</th><th className="pb-2 text-right">On hand</th><th className="pb-2 text-right">Value</th>
                                </tr></thead>
                                <tbody className="divide-y divide-border">
                                    {stock.map(s => (
                                        <tr key={s.product_id}>
                                            <td className="py-2">
                                                <Link href={`/inventory/products/${s.product_id}`} className="font-medium text-foreground hover:text-primary">{s.name}</Link>
                                                <span className="block font-mono text-xs text-muted-foreground">{s.sku}</span>
                                            </td>
                                            <td className="py-2 text-right">{s.on_hand} {s.unit_of_measure}</td>
                                            <td className="py-2 text-right font-medium">{formatCurrency(s.value)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}
                    </Card>
                </div>
            </div>

            {editOpen && <WarehouseFormModal open onClose={() => setEditOpen(false)} warehouse={warehouse} />}
            {locOpen && <LocationFormModal key={editLoc?.id ?? 'new'} open onClose={() => setLocOpen(false)} warehouseId={warehouse.id} location={editLoc} />}
            <ConfirmDialog open={deleting} onClose={() => setDeleting(false)} onConfirm={confirmDelete} processing={processing}
                title="Delete warehouse?" message={<>This soft-deletes <span className="font-medium text-foreground">{warehouse.name}</span>.</>} />
            <ConfirmDialog open={!!delLoc} onClose={() => setDelLoc(null)} onConfirm={confirmDelLoc}
                title="Remove location?" confirmLabel="Remove" message={delLoc ? <>Remove bin <span className="font-mono font-medium text-foreground">{delLoc.code}</span>?</> : ''} />
        </InventoryLayout>
    );
}
