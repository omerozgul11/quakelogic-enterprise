import { Head, Link } from '@inertiajs/react';
import { AssetLayout } from '@/Components/layout/AssetLayout';
import { PageHeader } from '@/Components/ui/PageHeader';
import { StatCard } from '@/Components/ui/StatCard';
import { Card } from '@/Components/ui/Card';
import { Pill } from '@/Components/ui/Pill';
import { EmptyState } from '@/Components/ui/EmptyState';
import { formatCurrency } from '@/Lib/utils';
import { Cpu, Radio, Wrench, CircleDollarSign, CalendarClock } from 'lucide-react';

interface Stats { total: number; operational: number; in_maintenance: number; value: number }
interface AssetRow { id: number; asset_tag: string; name: string; status_label: string; status_color: string; location: string | null }
interface MaintRow { id: number; asset_id: number; asset: string; type_label: string; type_color: string; next_due_at: string | null }

interface Props {
    stats: Stats;
    recent: AssetRow[];
    upcoming_maintenance: MaintRow[];
}

export default function AssetDashboard({ stats, recent, upcoming_maintenance }: Props) {
    return (
        <AssetLayout>
            <Head title="Assets" />
            <div className="p-4 sm:p-6">
                <PageHeader icon={Cpu} title="Asset Management" description="Deployed & internal instruments" />

                <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
                    <StatCard title="Assets" value={stats.total} icon={Cpu} tone="indigo" href="/assets/registry" />
                    <StatCard title="Operational" value={stats.operational} icon={Radio} tone="emerald" />
                    <StatCard title="In maintenance" value={stats.in_maintenance} icon={Wrench} tone={stats.in_maintenance > 0 ? 'amber' : 'sky'} />
                    <StatCard title="Asset value" value={formatCurrency(stats.value)} icon={CircleDollarSign} tone="teal" />
                </div>

                <div className="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <Card className="p-5">
                        <h2 className="mb-3 flex items-center gap-2 text-sm font-bold uppercase tracking-wider text-muted-foreground/70"><CalendarClock className="h-4 w-4" /> Upcoming maintenance</h2>
                        {upcoming_maintenance.length === 0 ? (
                            <p className="py-6 text-center text-sm text-muted-foreground">Nothing scheduled.</p>
                        ) : (
                            <div className="space-y-1.5">
                                {upcoming_maintenance.map(m => (
                                    <Link key={m.id} href={`/assets/registry/${m.asset_id}`} className="flex items-center gap-3 rounded-lg px-2 py-2 transition-colors hover:bg-secondary">
                                        <Pill color={m.type_color} label={m.type_label} />
                                        <span className="min-w-0 flex-1 truncate text-sm text-foreground">{m.asset}</span>
                                        <span className="text-xs text-muted-foreground">{m.next_due_at}</span>
                                    </Link>
                                ))}
                            </div>
                        )}
                    </Card>
                    <Card className="p-5">
                        <h2 className="mb-3 flex items-center gap-2 text-sm font-bold uppercase tracking-wider text-muted-foreground/70"><Cpu className="h-4 w-4" /> Recent assets</h2>
                        {recent.length === 0 ? (
                            <EmptyState icon={Cpu} title="No assets yet" description="Register an asset or commission one from inventory." />
                        ) : (
                            <div className="space-y-1.5">
                                {recent.map(a => (
                                    <Link key={a.id} href={`/assets/registry/${a.id}`} className="flex items-center gap-3 rounded-lg px-2 py-2 transition-colors hover:bg-secondary">
                                        <span className="min-w-0 flex-1">
                                            <span className="block truncate text-sm font-medium text-foreground">{a.name}</span>
                                            <span className="block truncate font-mono text-xs text-muted-foreground">{a.asset_tag}{a.location ? ` · ${a.location}` : ''}</span>
                                        </span>
                                        <Pill color={a.status_color} label={a.status_label} />
                                    </Link>
                                ))}
                            </div>
                        )}
                    </Card>
                </div>
            </div>
        </AssetLayout>
    );
}
