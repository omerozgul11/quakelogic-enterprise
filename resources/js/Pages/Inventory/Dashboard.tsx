import { Head, Link } from '@inertiajs/react';
import { InventoryLayout } from '@/Components/layout/InventoryLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { StatCard } from '@/Components/ui/StatCard';
import { Card } from '@/Components/ui/Card';
import { Pill } from '@/Components/ui/Pill';
import { EmptyState } from '@/Components/ui/EmptyState';
import { formatCurrency } from '@/Lib/utils';
import { Boxes, Package, Warehouse, AlertTriangle, CircleDollarSign, ArrowLeftRight } from 'lucide-react';

interface Stats { products: number; active_products: number; warehouses: number; low_stock: number; valuation: number }
interface LowStockRow { id: number; sku: string; name: string; on_hand: number; reorder_point: number; unit_of_measure: string }
interface MovementRow { id: number; type_label: string; type_color: string; quantity: number; product: string; warehouse: string | null; occurred_at: string | null }

interface Props {
    stats: Stats;
    low_stock: LowStockRow[];
    recent_movements: MovementRow[];
}

function when(iso: string | null): string {
    if (!iso) return '';
    return new Date(iso).toLocaleString(undefined, { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
}

export default function InventoryDashboard({ stats, low_stock, recent_movements }: Props) {
    return (
        <InventoryLayout>
            <Head title="Inventory" />
            <div className="p-4 sm:p-6">
                <PageHeader icon={Boxes} title="Inventory" description="Product master, stock & movements" />

                <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
                    <StatCard title="Products" value={stats.products} subtitle={`${stats.active_products} active`} icon={Package} tone="indigo" href="/inventory/products" />
                    <StatCard title="Warehouses" value={stats.warehouses} icon={Warehouse} tone="sky" href="/inventory/warehouses" />
                    <StatCard title="Low stock" value={stats.low_stock} subtitle="at/below reorder point" icon={AlertTriangle} tone={stats.low_stock > 0 ? 'amber' : 'emerald'} href="/inventory/products?status=low" />
                    <StatCard title="Inventory value" value={formatCurrency(stats.valuation)} subtitle="at average cost" icon={CircleDollarSign} tone="teal" />
                </div>

                <div className="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <Card className="p-5">
                        <h2 className="mb-3 flex items-center gap-2 text-sm font-bold uppercase tracking-wider text-muted-foreground/70">
                            <AlertTriangle className="h-4 w-4" /> Low stock
                        </h2>
                        {low_stock.length === 0 ? (
                            <p className="py-6 text-center text-sm text-muted-foreground">Everything is above its reorder point. 🎉</p>
                        ) : (
                            <div className="space-y-1.5">
                                {low_stock.map(p => (
                                    <Link key={p.id} href={`/inventory/products/${p.id}`} className="flex items-center gap-3 rounded-lg px-2 py-2 transition-colors hover:bg-secondary">
                                        <span className="min-w-0 flex-1">
                                            <span className="block truncate text-sm font-medium text-foreground">{p.name}</span>
                                            <span className="block truncate font-mono text-xs text-muted-foreground">{p.sku}</span>
                                        </span>
                                        <Pill color="amber" label={`${p.on_hand} / ${p.reorder_point} ${p.unit_of_measure}`} />
                                    </Link>
                                ))}
                            </div>
                        )}
                    </Card>

                    <Card className="p-5">
                        <h2 className="mb-3 flex items-center gap-2 text-sm font-bold uppercase tracking-wider text-muted-foreground/70">
                            <ArrowLeftRight className="h-4 w-4" /> Recent movements
                        </h2>
                        {recent_movements.length === 0 ? (
                            <EmptyState icon={ArrowLeftRight} title="No movements yet" description="Receiving, issues and transfers will appear here." />
                        ) : (
                            <div className="space-y-1.5">
                                {recent_movements.map(m => (
                                    <div key={m.id} className="flex items-center gap-3 rounded-lg px-2 py-2">
                                        <Pill color={m.type_color} label={m.type_label} />
                                        <span className="min-w-0 flex-1 truncate text-sm text-foreground">{m.product}</span>
                                        <span className={m.quantity < 0 ? 'text-sm font-semibold text-red-600' : 'text-sm font-semibold text-emerald-600'}>
                                            {m.quantity > 0 ? '+' : ''}{m.quantity}
                                        </span>
                                        <span className="hidden text-xs text-muted-foreground sm:inline">{when(m.occurred_at)}</span>
                                    </div>
                                ))}
                            </div>
                        )}
                    </Card>
                </div>
            </div>
        </InventoryLayout>
    );
}
