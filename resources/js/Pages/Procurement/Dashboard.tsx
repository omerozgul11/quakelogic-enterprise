import { Head, Link } from '@inertiajs/react';
import { ProcurementLayout } from '@/Components/layout/ProcurementLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { StatCard } from '@/Components/ui/StatCard';
import { Card } from '@/Components/ui/Card';
import { Pill } from '@/Components/ui/Pill';
import { EmptyState } from '@/Components/ui/EmptyState';
import { formatCurrency } from '@/Lib/utils';
import { ShoppingCart, Factory, ClipboardCheck, PackageOpen, CircleDollarSign } from 'lucide-react';

interface Stats { suppliers: number; active_suppliers: number; open_pos: number; pending_approval: number; open_value: number }
interface PoRow { id: number; number: string; supplier: string | null; status_label: string; status_color: string; total: number; currency: string; order_date: string | null }

interface Props {
    stats: Stats;
    recent: PoRow[];
    pending_approval_list: PoRow[];
}

export default function ProcurementDashboard({ stats, recent, pending_approval_list }: Props) {
    return (
        <ProcurementLayout>
            <Head title="Procurement" />
            <div className="p-4 sm:p-6">
                <PageHeader icon={ShoppingCart} title="Procurement" description="Suppliers & purchasing" />

                <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
                    <StatCard title="Suppliers" value={stats.suppliers} subtitle={`${stats.active_suppliers} active`} icon={Factory} tone="indigo" href="/procurement/suppliers" />
                    <StatCard title="Open POs" value={stats.open_pos} icon={PackageOpen} tone="sky" href="/procurement/purchase-orders" />
                    <StatCard title="Pending approval" value={stats.pending_approval} icon={ClipboardCheck} tone={stats.pending_approval > 0 ? 'amber' : 'emerald'} />
                    <StatCard title="Open PO value" value={formatCurrency(stats.open_value)} icon={CircleDollarSign} tone="teal" />
                </div>

                <div className="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <Card className="p-5">
                        <h2 className="mb-3 flex items-center gap-2 text-sm font-bold uppercase tracking-wider text-muted-foreground/70">
                            <ClipboardCheck className="h-4 w-4" /> Awaiting approval
                        </h2>
                        {pending_approval_list.length === 0 ? (
                            <p className="py-6 text-center text-sm text-muted-foreground">Nothing awaiting approval.</p>
                        ) : (
                            <div className="space-y-1.5">{pending_approval_list.map(po => <PoLine key={po.id} po={po} />)}</div>
                        )}
                    </Card>

                    <Card className="p-5">
                        <h2 className="mb-3 flex items-center gap-2 text-sm font-bold uppercase tracking-wider text-muted-foreground/70">
                            <ShoppingCart className="h-4 w-4" /> Recent purchase orders
                        </h2>
                        {recent.length === 0 ? (
                            <EmptyState icon={ShoppingCart} title="No purchase orders yet" description="Create your first PO to start buying." />
                        ) : (
                            <div className="space-y-1.5">{recent.map(po => <PoLine key={po.id} po={po} />)}</div>
                        )}
                    </Card>
                </div>
            </div>
        </ProcurementLayout>
    );
}

function PoLine({ po }: { po: PoRow }) {
    return (
        <Link href={`/procurement/purchase-orders/${po.id}`} className="flex items-center gap-3 rounded-lg px-2 py-2 transition-colors hover:bg-secondary">
            <span className="min-w-0 flex-1">
                <span className="block truncate text-sm font-medium text-foreground">{po.number}</span>
                <span className="block truncate text-xs text-muted-foreground">{po.supplier ?? '—'}</span>
            </span>
            <span className="text-sm font-semibold text-foreground">{formatCurrency(po.total, po.currency)}</span>
            <Pill color={po.status_color} label={po.status_label} />
        </Link>
    );
}
