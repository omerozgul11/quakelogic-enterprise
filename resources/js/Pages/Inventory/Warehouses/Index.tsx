import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import { InventoryLayout } from '@/Components/layout/InventoryLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { Pill } from '@/Components/ui/Pill';
import { EmptyState } from '@/Components/ui/EmptyState';
import { WarehouseFormModal } from '@/Components/inventory/WarehouseFormModal';
import { formatCurrency } from '@/Lib/utils';
import { Warehouse as WarehouseIcon, Plus, MapPin, Boxes } from 'lucide-react';

interface WarehouseRow {
    id: number; code: string; name: string; type: string;
    city: string | null; state: string | null;
    is_default: boolean; is_active: boolean;
    locations_count: number; sku_count: number; value: number;
}

interface Props {
    warehouses: WarehouseRow[];
    can: { manage: boolean };
}

export default function WarehousesIndex({ warehouses, can }: Props) {
    const [formOpen, setFormOpen] = useState(false);

    return (
        <InventoryLayout>
            <Head title="Warehouses · Inventory" />
            <div className="p-4 sm:p-6">
                <PageHeader
                    icon={WarehouseIcon}
                    title="Warehouses"
                    description={`${warehouses.length} ${warehouses.length === 1 ? 'location' : 'locations'}`}
                    actions={can.manage && <Button onClick={() => setFormOpen(true)} icon={Plus}>Add Warehouse</Button>}
                />

                {warehouses.length === 0 ? (
                    <Card className="p-2">
                        <EmptyState
                            icon={WarehouseIcon}
                            title="No warehouses yet"
                            description="Create a warehouse to start holding stock and recording movements."
                            action={can.manage && <Button onClick={() => setFormOpen(true)} icon={Plus}>Add Warehouse</Button>}
                        />
                    </Card>
                ) : (
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {warehouses.map(w => (
                            <Link key={w.id} href={`/inventory/warehouses/${w.id}`} className="card-surface card-hover group p-5">
                                <div className="flex items-start justify-between">
                                    <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br from-sky-500 to-blue-500 text-white">
                                        <WarehouseIcon className="h-5 w-5" />
                                    </div>
                                    <div className="flex items-center gap-1.5">
                                        {w.is_default && <Pill color="blue" label="Default" />}
                                        {!w.is_active && <span className="chip">Inactive</span>}
                                    </div>
                                </div>
                                <h3 className="mt-3 font-semibold text-foreground group-hover:text-primary">{w.name}</h3>
                                <p className="font-mono text-xs text-muted-foreground">{w.code} · {w.type}</p>
                                {(w.city || w.state) && (
                                    <p className="mt-1 inline-flex items-center gap-1 text-xs text-muted-foreground">
                                        <MapPin className="h-3 w-3" /> {[w.city, w.state].filter(Boolean).join(', ')}
                                    </p>
                                )}
                                <div className="mt-4 flex items-center justify-between border-t border-border pt-3 text-sm">
                                    <span className="inline-flex items-center gap-1 text-muted-foreground"><Boxes className="h-4 w-4" /> {w.sku_count} SKUs · {w.locations_count} bins</span>
                                    <span className="font-semibold text-foreground">{formatCurrency(w.value)}</span>
                                </div>
                            </Link>
                        ))}
                    </div>
                )}
            </div>

            {formOpen && <WarehouseFormModal open onClose={() => setFormOpen(false)} />}
        </InventoryLayout>
    );
}
