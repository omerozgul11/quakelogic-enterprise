import { Head, Link } from '@inertiajs/react';
import { ManufacturingLayout } from '@/Components/layout/ManufacturingLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { StatCard } from '@/Components/ui/StatCard';
import { Card } from '@/Components/ui/Card';
import { Pill } from '@/Components/ui/Pill';
import { EmptyState } from '@/Components/ui/EmptyState';
import { Factory, ListTree, Hammer, CheckCircle2 } from 'lucide-react';

interface Stats { boms: number; active_boms: number; open_work_orders: number; completed: number }
interface WoRow { id: number; number: string; product: string | null; status_label: string; status_color: string; quantity_planned: number; quantity_produced: number }

interface Props {
    stats: Stats;
    open: WoRow[];
    recent: WoRow[];
}

export default function ManufacturingDashboard({ stats, open, recent }: Props) {
    return (
        <ManufacturingLayout>
            <Head title="Manufacturing" />
            <div className="p-4 sm:p-6">
                <PageHeader icon={Factory} title="Manufacturing" description="Bills of materials & work orders" />

                <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
                    <StatCard title="BOMs" value={stats.boms} subtitle={`${stats.active_boms} active`} icon={ListTree} tone="indigo" href="/manufacturing/boms" />
                    <StatCard title="Open work orders" value={stats.open_work_orders} icon={Hammer} tone="amber" href="/manufacturing/work-orders" />
                    <StatCard title="Completed" value={stats.completed} icon={CheckCircle2} tone="emerald" />
                    <StatCard title="Active BOMs" value={stats.active_boms} icon={ListTree} tone="sky" />
                </div>

                <div className="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <Card className="p-5">
                        <h2 className="mb-3 flex items-center gap-2 text-sm font-bold uppercase tracking-wider text-muted-foreground/70"><Hammer className="h-4 w-4" /> Open work orders</h2>
                        {open.length === 0 ? (
                            <EmptyState icon={Hammer} title="No open work orders" description="Create a work order to start a build." />
                        ) : (
                            <div className="space-y-1.5">{open.map(w => <WoLine key={w.id} w={w} />)}</div>
                        )}
                    </Card>
                    <Card className="p-5">
                        <h2 className="mb-3 flex items-center gap-2 text-sm font-bold uppercase tracking-wider text-muted-foreground/70"><Factory className="h-4 w-4" /> Recent</h2>
                        {recent.length === 0 ? (
                            <p className="py-6 text-center text-sm text-muted-foreground">No work orders yet.</p>
                        ) : (
                            <div className="space-y-1.5">{recent.map(w => <WoLine key={w.id} w={w} />)}</div>
                        )}
                    </Card>
                </div>
            </div>
        </ManufacturingLayout>
    );
}

function WoLine({ w }: { w: WoRow }) {
    return (
        <Link href={`/manufacturing/work-orders/${w.id}`} className="flex items-center gap-3 rounded-lg px-2 py-2 transition-colors hover:bg-secondary">
            <span className="min-w-0 flex-1">
                <span className="block truncate text-sm font-medium text-foreground">{w.number}</span>
                <span className="block truncate text-xs text-muted-foreground">{w.product ?? '—'}</span>
            </span>
            <span className="text-xs text-muted-foreground">{w.quantity_produced}/{w.quantity_planned}</span>
            <Pill color={w.status_color} label={w.status_label} />
        </Link>
    );
}
